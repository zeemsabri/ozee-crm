<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\Resource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class CommentController extends Controller
{
    /**
     * Get all comments for a resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $resourceId
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request, $resourceId)
    {
        try {
            $resource = Resource::findOrFail($resourceId);

            // Check if user has permission to view the resource
            // This assumes you have a policy or other authorization mechanism
            // $this->authorize('view', $resource);

            // Check if the resource is visible to the client if the user is a client
            if (Auth::user()->hasRole('client') && !$resource->visible_to_client) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have permission to view this resource'
                ], 403);
            }

            $comments = $resource->comments()->with('user')->get();

            return response()->json([
                'success' => true,
                'comments' => $comments
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching comments: ' . $e->getMessage(), [
                'resource_id' => $resourceId,
                'error' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch comments: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a new comment.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $resourceId
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request, $resourceId)
    {
        try {
            $resource = Resource::findOrFail($resourceId);

            // Check if the resource is visible to the client if the user is a client
            if (Auth::user()->hasRole('client') && !$resource->visible_to_client) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have permission to comment on this resource'
                ], 403);
            }

            // Validate the request
            $validator = Validator::make($request->all(), [
                'content' => 'required|string|max:1000',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $validated = $validator->validated();

            // Create the comment
            $comment = new Comment([
                'content' => $validated['content'],
                'user_id' => Auth::id(),
            ]);

            // Save the comment
            $resource->comments()->save($comment);

            return response()->json([
                'success' => true,
                'message' => 'Comment created successfully',
                'comment' => $comment->load('user')
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error creating comment: ' . $e->getMessage(), [
                'resource_id' => $resourceId,
                'error' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create comment: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update a comment.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $commentId
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $commentId)
    {
        try {
            $comment = Comment::findOrFail($commentId);

            // Check if user has permission to update the comment
            if (Auth::id() !== $comment->user_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have permission to update this comment'
                ], 403);
            }

            // Validate the request
            $validator = Validator::make($request->all(), [
                'content' => 'required|string|max:1000',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $validated = $validator->validated();

            // Update the comment
            $comment->content = $validated['content'];
            $comment->save();

            return response()->json([
                'success' => true,
                'message' => 'Comment updated successfully',
                'comment' => $comment->load('user')
            ]);
        } catch (\Exception $e) {
            Log::error('Error updating comment: ' . $e->getMessage(), [
                'comment_id' => $commentId,
                'error' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update comment: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a comment.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $commentId
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request, $commentId)
    {
        try {
            $comment = Comment::findOrFail($commentId);

            // Check if user has permission to delete the comment
            if (Auth::id() !== $comment->user_id && !Auth::user()->hasRole('admin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have permission to delete this comment'
                ], 403);
            }

            // Delete the comment
            $comment->delete();

            return response()->json([
                'success' => true,
                'message' => 'Comment deleted successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error deleting comment: ' . $e->getMessage(), [
                'comment_id' => $commentId,
                'error' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete comment: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Approve a resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $resourceId
     * @return \Illuminate\Http\Response
     */
    public function approveResource(Request $request, $resourceId)
    {
        try {
            $resource = Resource::findOrFail($resourceId);

            // Check if user has permission to approve the resource
            if (!Auth::user()->hasRole('admin') && !Auth::user()->hasRole('manager')) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have permission to approve this resource'
                ], 403);
            }

            // Update the resource
            $resource->requires_approval = false;
            $resource->save();

            return response()->json([
                'success' => true,
                'message' => 'Resource approved successfully',
                'resource' => $resource
            ]);
        } catch (\Exception $e) {
            Log::error('Error approving resource: ' . $e->getMessage(), [
                'resource_id' => $resourceId,
                'error' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to approve resource: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Toggle client visibility for a resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $resourceId
     * @return \Illuminate\Http\Response
     */
    public function toggleVisibility(Request $request, $resourceId)
    {
        try {
            $resource = Resource::findOrFail($resourceId);

            // Check if user has permission to toggle visibility
            if (!Auth::user()->hasRole('admin') && !Auth::user()->hasRole('manager')) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have permission to toggle visibility for this resource'
                ], 403);
            }

            // Toggle visibility
            $resource->visible_to_client = !$resource->visible_to_client;
            $resource->save();

            return response()->json([
                'success' => true,
                'message' => 'Resource visibility toggled successfully',
                'resource' => $resource
            ]);
        } catch (\Exception $e) {
            Log::error('Error toggling resource visibility: ' . $e->getMessage(), [
                'resource_id' => $resourceId,
                'error' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to toggle resource visibility: ' . $e->getMessage()
            ], 500);
        }
    }
}
