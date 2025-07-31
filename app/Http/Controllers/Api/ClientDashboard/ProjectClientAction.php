<?php

namespace App\Http\Controllers\Api\ClientDashboard;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\Milestone;
use App\Models\Project;
use App\Models\ProjectNote;
use App\Models\Task;
use App\Models\TaskType;
use App\Services\GoogleDriveService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Models\Deliverable;
use App\Models\Client;
use App\Models\ClientDeliverableInteraction;
// use App\Models\DeliverableComment; // Replaced with ProjectNote
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ProjectClientAction extends Controller
{
    /**
     * Mark a deliverable as read by the client.
     *
     * @param \Illuminate\Http\Request $request
     * @param \App\Models\Deliverable $deliverable
     * @return \Illuminate\Http\JsonResponse
     */
    public function markDeliverableAsRead(Request $request, Deliverable $deliverable)
    {
        try {
            $authenticatedProjectId = $request->attributes->get('magic_link_project_id');
            $authenticatedClientEmail = $request->attributes->get('magic_link_email');

            // Verify the deliverable belongs to the authenticated project
            if ((int)$deliverable->project_id !== (int)$authenticatedProjectId) {
                return response()->json(['message' => 'Unauthorized access to deliverable.'], 403);
            }

            // Find the client based on the authenticated email
            $client = Client::where('email', $authenticatedClientEmail)->first();

            if (!$client) {
                return response()->json(['message' => 'Authenticated client not found.'], 404);
            }

            // Find or create the interaction record
            $interaction = ClientDeliverableInteraction::firstOrCreate(
                [
                    'deliverable_id' => $deliverable->id,
                    'client_id' => $client->id,
                ],
                [
                    'read_at' => now(), // Set read_at only on first creation
                ]
            );

            // If it already existed and wasn't read, update read_at
            if (!$interaction->read_at) {
                $interaction->read_at = now();
                $interaction->save();
            }

            return response()->json(['message' => 'Deliverable marked as read.', 'interaction' => $interaction]);

        } catch (\Exception $e) {
            Log::error("Error marking deliverable as read: {$e->getMessage()}", [
                'deliverable_id' => $deliverable->id,
                'client_email' => $request->attributes->get('magic_link_email'),
                'error' => $e->getTraceAsString(),
            ]);
            return response()->json(['message' => 'Failed to mark deliverable as read.', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Client approves a deliverable.
     *
     * @param \Illuminate\Http\Request $request
     * @param \App\Models\Deliverable $deliverable
     * @return \Illuminate\Http\JsonResponse
     */
    public function approveDeliverable(Request $request, Deliverable $deliverable)
    {
        try {
            $request->validate([
                'feedback_text' => 'nullable|string|max:2000',
            ]);

            $authenticatedProjectId = $request->attributes->get('magic_link_project_id');
            $authenticatedClientEmail = $request->attributes->get('magic_link_email');

            if ((int)$deliverable->project_id !== (int)$authenticatedProjectId) {
                return response()->json(['message' => 'Unauthorized access to deliverable.'], 403);
            }

            $client = Client::where('email', $authenticatedClientEmail)->first();
            if (!$client) {
                return response()->json(['message' => 'Authenticated client not found.'], 404);
            }

            // Find or create the interaction record
            $interaction = ClientDeliverableInteraction::firstOrCreate(
                [
                    'deliverable_id' => $deliverable->id,
                    'client_id' => $client->id,
                ]
            );

            // Update the interaction
            $interaction->update([
                'read_at' => $interaction->read_at ?? now(), // Ensure read_at is set
                'approved_at' => now(),
                'rejected_at' => null, // Clear rejection status if previously rejected
                'revisions_requested_at' => null, // Clear revision request if previously requested
                'feedback_text' => $request->input('feedback_text'),
            ]);

            // TODO: Optional: Logic to update overall Deliverable status if all clients have approved
            // You'd need to fetch all clients for the project, check their interaction statuses.

            return response()->json(['message' => 'Deliverable approved successfully.', 'interaction' => $interaction]);

        } catch (ValidationException $e) {
            return response()->json(['message' => 'Validation failed.', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            Log::error("Error approving deliverable: {$e->getMessage()}", [
                'deliverable_id' => $deliverable->id,
                'client_email' => $request->attributes->get('magic_link_email'),
                'error' => $e->getTraceAsString(),
            ]);
            return response()->json(['message' => 'Failed to approve deliverable.', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Client requests revisions for a deliverable (or rejects it).
     *
     * @param \Illuminate\Http\Request $request
     * @param \App\Models\Deliverable $deliverable
     * @return \Illuminate\Http\JsonResponse
     */
    public function requestDeliverableRevisions(Request $request, Deliverable $deliverable)
    {
        try {
            $request->validate([
                'feedback_text' => 'required|string|max:2000', // Feedback is required for revisions
            ]);

            $authenticatedProjectId = $request->attributes->get('magic_link_project_id');
            $authenticatedClientEmail = $request->attributes->get('magic_link_email');

            if ((int)$deliverable->project_id !== (int)$authenticatedProjectId) {
                return response()->json(['message' => 'Unauthorized access to deliverable.'], 403);
            }

            $client = Client::where('email', $authenticatedClientEmail)->first();
            if (!$client) {
                return response()->json(['message' => 'Authenticated client not found.'], 404);
            }

            // Find or create the interaction record
            $interaction = ClientDeliverableInteraction::firstOrCreate(
                [
                    'deliverable_id' => $deliverable->id,
                    'client_id' => $client->id,
                ]
            );

            // Update the interaction
            $interaction->update([
                'read_at' => $interaction->read_at ?? now(), // Ensure read_at is set
                'approved_at' => null, // Clear approval status if previously approved
                'rejected_at' => null, // Clear rejection status if previously rejected
                'revisions_requested_at' => now(),
                'feedback_text' => $request->input('feedback_text'),
            ]);

            // TODO: Optional: Logic to update overall Deliverable status if revisions are requested
            // E.g., change deliverable status to 'revisions_requested' if it was 'pending_review'

            return response()->json(['message' => 'Revisions requested successfully.', 'interaction' => $interaction]);

        } catch (ValidationException $e) {
            return response()->json(['message' => 'Feedback is required for revision requests.', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            Log::error("Error requesting revisions for deliverable: {$e->getMessage()}", [
                'deliverable_id' => $deliverable->id,
                'client_email' => $request->attributes->get('magic_link_email'),
                'error' => $e->getTraceAsString(),
            ]);
            return response()->json(['message' => 'Failed to request revisions.', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Client adds a general comment to a deliverable.
     *
     * @param \Illuminate\Http\Request $request
     * @param \App\Models\Deliverable $deliverable
     * @return \Illuminate\Http\JsonResponse
     */
    public function addDeliverableComment(Request $request, Deliverable $deliverable)
    {
        try {
            $request->validate([
                'comment_text' => 'required|string|max:2000',
                'context' => 'nullable|string|max:255', // e.g., "paragraph 2", "image 1"
            ]);

            $authenticatedProjectId = $request->attributes->get('magic_link_project_id');
            $authenticatedClientEmail = $request->attributes->get('magic_link_email');

            if ((int)$deliverable->project_id !== (int)$authenticatedProjectId) {
                return response()->json(['message' => 'Unauthorized access to deliverable.'], 403);
            }

            $client = Client::where('email', $authenticatedClientEmail)->first();
            if (!$client) {
                return response()->json(['message' => 'Authenticated client not found.'], 404);
            }

            $comment = new ProjectNote([
                'content' => $request->input('comment_text'),
                'type' => 'comment',
                'noteable_id' => $deliverable->id,
                'noteable_type' => get_class($deliverable),
                'creator_id' => $client->id,
                'creator_type' => get_class($client),
                'project_id' => $deliverable->project_id,
            ]);

            // Store the context in a way that's compatible with ProjectNote
            if ($request->has('context')) {
                $comment->context = $request->input('context');
            }

            $comment->save();

            // Ensure the deliverable is marked as read if a comment is added
            $interaction = ClientDeliverableInteraction::firstOrCreate(
                [
                    'deliverable_id' => $deliverable->id,
                    'client_id' => $client->id,
                ]
            );
            if (!$interaction->read_at) {
                $interaction->read_at = now();
                $interaction->save();
            }

            return response()->json(['message' => 'Comment added successfully.', 'comment' => $comment], 201);

        } catch (ValidationException $e) {
            return response()->json(['message' => 'Comment text is required.', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            Log::error("Error adding comment to deliverable: {$e->getMessage()}", [
                'deliverable_id' => $deliverable->id,
                'client_email' => $request->attributes->get('magic_link_email'),
                'error' => $e->getTraceAsString(),
            ]);
            return response()->json(['message' => 'Failed to add comment.', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Add a note/reply to a specific task.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Task  $task
     * @return \Illuminate\Http\JsonResponse
     */
    public function addNoteToTask(Request $request, Task $task)
    {
        $authenticatedProjectId = $request->attributes->get('magic_link_project_id');
        $authenticatedClientEmail = $request->attributes->get('magic_link_email');


        $projectId = $task->milestone?->project?->id;

        // Security check: Ensure the task belongs to the authenticated project
        if ((int)$projectId !== (int)$authenticatedProjectId) {
            return response()->json(['message' => 'Unauthorized action on this task.'], 403);
        }

        // Validate the request input
        try {
            $request->validate([
                'comment_text' => 'required|string|max:2000',
            ]);
        } catch (ValidationException $e) {
            return response()->json(['message' => 'Validation failed', 'errors' => $e->errors()], 422);
        }

        // Find the client based on the authenticated email
        $client = Client::where('email', $authenticatedClientEmail)->first();
        if (!$client) {
            return response()->json(['message' => 'Client not found.'], 404);
        }

        try {

            $note = $task->addNote($request->comment_text, $client);
            return response()->json([
                'message' => 'Comment added successfully.',
                'note' => $note,
            ], 201);


        } catch (\Exception $e) {
            Log::error('Error adding note to task: ' . $e->getMessage(), [
                'task_id' => $task->id,
                'client_email' => $authenticatedClientEmail,
                'error' => $e->getTraceAsString()
            ]);
            return response()->json(['message' => 'Failed to add comment to task.'], 500);
        }
    }

    /**
     * Add a note/reply to a specific task.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Task  $task
     * @return \Illuminate\Http\JsonResponse
     */
    public function addNoteToDocument(Request $request, Document $document)
    {
        $authenticatedProjectId = $request->attributes->get('magic_link_project_id');
        $authenticatedClientEmail = $request->attributes->get('magic_link_email');


        $projectId = $document->project_id;

        // Security check: Ensure the task belongs to the authenticated project
        if ((int)$projectId !== (int)$authenticatedProjectId) {
            return response()->json(['message' => 'Unauthorized action on this document.'], 403);
        }

        // Validate the request input
        try {
            $request->validate([
                'comment_text' => 'required|string|max:2000',
            ]);
        } catch (ValidationException $e) {
            return response()->json(['message' => 'Validation failed', 'errors' => $e->errors()], 422);
        }

        // Find the client based on the authenticated email
        $client = Client::where('email', $authenticatedClientEmail)->first();
        if (!$client) {
            return response()->json(['message' => 'Client not found.'], 404);
        }

        try {

            $note = $document->addNote($request->comment_text, $client);
            return response()->json([
                'message' => 'Comment added successfully.',
                'note' => $note,
            ], 201);


        } catch (\Exception $e) {
            Log::error('Error adding note to task: ' . $e->getMessage(), [
                'task_id' => $document->id,
                'client_email' => $authenticatedClientEmail,
                'error' => $e->getTraceAsString()
            ]);
            return response()->json(['message' => 'Failed to add comment to task.'], 500);
        }
    }

    /**
     * Create a new task by a client.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function createTask(Request $request)
    {
        $authenticatedProjectId = $request->attributes->get('magic_link_project_id');
        $authenticatedClientEmail = $request->attributes->get('magic_link_email');

        if (!$authenticatedProjectId || !$authenticatedClientEmail) {
            return response()->json(['message' => 'Authentication context missing.'], 401);
        }

        $client = Client::where('email', $authenticatedClientEmail)->first();
        if (!$client) {
            return response()->json(['message' => 'Client not found.'], 404);
        }

        try {
            $request->validate([
                'title' => 'required|string|max:255',
                'description' => 'nullable|string',
                'due_date' => 'nullable|date',
                // 'status' is usually set to a default (e.g., 'pending') by the model/migration
            ]);

            $project = Project::findOrFail($authenticatedProjectId);
            $milestone = $project->supportMilestone();

            $task = new Task();
            $task->milestone_id = $milestone->id;
            $task->name = $request->input('title');
            $task->description = $request->input('description');
            $task->task_type_id = TaskType::where('name', Project::SUPPORT)->first()?->id ?? 1;
            $task->due_date = $request->input('due_date');
            $task->status = 'To Do'; // Default status for client-created tasks

            // The 'creating' model event in Task.php will handle setting creator_id and creator_type
            $task->save();

            return response()->json([
                'message' => 'Task created successfully.',
                'task' => $task->load('notes'), // Load notes for immediate display if needed
            ], 201);
        } catch (ValidationException $e) {
            return response()->json(['message' => 'Validation failed', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            Log::error('Error creating task: ' . $e->getMessage(), [
                'project_id' => $authenticatedProjectId,
                'client_email' => $authenticatedClientEmail,
                'error' => $e->getTraceAsString()
            ]);
            return response()->json(['message' => 'Failed to create task.'], 500);
        }
    }

    /**
     * Upload documents from the client dashboard.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function uploadClientDocuments(Request $request)
    {
        $authenticatedProjectId = $request->attributes->get('magic_link_project_id');
        $authenticatedClientEmail = $request->attributes->get('magic_link_email');

        // Verify the project exists and is accessible
        $project = Project::find($authenticatedProjectId);
        if (!$project) {
            return response()->json(['message' => 'Project not found or unauthorized.'], 403);
        }

        try {
            $validationRules = [
                'documents' => 'required|array',
                'documents.*' => 'required|file|mimes:pdf,doc,docx,jpg,png,jpeg|max:10240', // Max 10MB
            ];
            $validated = $request->validate($validationRules);
            $uploadedDocuments = [];

            if ($request->hasFile('documents')) {
                // Get the client who is uploading
                $client = Client::where('email', $authenticatedClientEmail)->first();
                if (!$client) {
                    return response()->json(['message' => 'Client not found.'], 404);
                }

                $uploadedDocuments = $project->uploadDocuments($request->file('documents'), new GoogleDriveService());

                return response()->json([
                    'message' => 'Documents uploaded successfully',
                    'documents' => $uploadedDocuments // Return the newly uploaded documents
                ]);
            }

            return response()->json([
                'message' => 'No documents were uploaded',
                'documents' => []
            ]);
        } catch (ValidationException $e) {
            return response()->json(['message' => 'Validation failed', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            Log::error('Error uploading documents: ' . $e->getMessage(), [
                'project_id' => $authenticatedProjectId,
                'email' => $authenticatedClientEmail,
                'error' => $e->getTraceAsString()
            ]);
            return response()->json(['message' => 'Failed to upload documents: ' . $e->getMessage()], 500);
        }
    }
}
