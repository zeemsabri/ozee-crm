<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ClientResource;
use App\Models\Client;
use App\Models\Conversation;
use App\Models\Lead;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth; // For Auth::user()

class ClientController extends Controller
{
    public function __construct()
    {
        // Removed authorizeResource to handle authorization in each method
        // based on role-specific logic instead of relying solely on ClientPolicy
    }

    /**
     * Display a listing of the clients.
     */
    public function index()
    {
        $user = Auth::user();

        // Check if user has permission to view clients
        if ($user->hasPermission('view_clients')) {
            // Admins, Managers, Employees can see all clients
            $clients = Client::all();
        } elseif ($user->isContractor()) {
            // Contractors can see clients associated with their assigned projects
            // even without the explicit 'view_clients' permission
            $clients = $user->clients;
        } else {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return ClientResource::collection($clients);
    }

    /**
     * Store a newly created client in storage.
     */
    public function store(Request $request)
    {
        $user = Auth::user();

        // Only users with create_clients permission can create clients
        if (!$user->hasPermission('create_clients')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:clients',
                'phone' => 'nullable|string|max:50',
                'address' => 'nullable|string',
                'notes' => 'nullable|string',
            ]);

            $client = Client::create($validated);
            Log::info('Client created', ['client_id' => $client->id, 'client_name' => $client->name, 'user_id' => Auth::id()]);
            return (new ClientResource($client))->response()->setStatusCode(201);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error creating client: ' . $e->getMessage(), ['request' => $request->all(), 'error' => $e->getTraceAsString()]);
            return response()->json(['message' => 'Failed to create client', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Display the specified client.
     */
    public function show(Client $client)
    {
        $user = Auth::user();

        // Check if user has permission to view clients
        if ($user->hasPermission('view_clients')) {
            return new ClientResource($client);
        } elseif ($user->isContractor()) {
            // Contractors can only view clients associated with their projects
            if ($user->clients->contains('id', $client->id)) {
                return new ClientResource($client);
            }
        }

        return response()->json(['message' => 'Unauthorized'], 403);
    }

    /**
     * Update the specified client in storage.
     */
    public function update(Request $request, Client $client)
    {
        $user = Auth::user();

        // Only users with edit_clients permission can update clients
        if (!$user->hasPermission('edit_clients')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        try {
            $validated = $request->validate([
                'name' => 'sometimes|required|string|max:255',
                'email' => 'sometimes|required|string|email|max:255|unique:clients,email,' . $client->id,
                'phone' => 'nullable|string|max:50',
                'address' => 'nullable|string',
                'notes' => 'nullable|string',
            ]);

            $client->update($validated);
            Log::info('Client updated', ['client_id' => $client->id, 'client_name' => $client->name, 'user_id' => Auth::id()]);
            return new ClientResource($client);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error updating client: ' . $e->getMessage(), ['client_id' => $client->id, 'request' => $request->all(), 'error' => $e->getTraceAsString()]);
            return response()->json(['message' => 'Failed to update client', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Remove the specified client from storage.
     */
    public function destroy(Client $client)
    {
        $user = Auth::user();

        // Only users with delete_clients permission can delete clients
        if (!$user->hasPermission('delete_clients')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        try {
            $client->delete();
            Log::info('Client deleted', ['client_id' => $client->id, 'client_name' => $client->name, 'user_id' => Auth::id()]);
            return response()->json(null, 204);
        } catch (\Exception $e) {
            Log::error('Error deleting client: ' . $e->getMessage(), ['client_id' => $client->id, 'error' => $e->getTraceAsString()]);
            return response()->json(['message' => 'Failed to delete client', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get the email of a specific client.
     * This endpoint is used internally by the application when sending emails.
     */
    public function getEmail(Client $client)
    {
        $user = Auth::user();

        // Check if user has permission to view clients
        if ($user->hasPermission('view_clients')) {
            // Allow access for users with view_clients permission
        } elseif ($user->isContractor()) {
            // Contractors can only access emails of clients associated with their projects
            if (!$user->clients->contains('id', $client->id)) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
        } else {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        try {
            return response()->json(['email' => $client->email]);
        } catch (\Exception $e) {
            Log::error('Error getting client email: ' . $e->getMessage(), ['client_id' => $client->id, 'error' => $e->getTraceAsString()]);
            return response()->json(['message' => 'Failed to get client email', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get emails related to a given client (by conversation conversable).
     */
    public function emails(Client $client, Request $request)
    {
        $user = Auth::user();
        // Permission check similar to show/details
        if ($user->hasPermission('view_clients')) {
            // ok
        } elseif ($user->isContractor()) {
            if (!$user->clients->contains('id', $client->id)) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
        } else {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $perPage = (int)($request->input('per_page', 15));
        $page = (int)($request->input('page', 1));

        $query = \App\Models\Email::with(['sender', 'approver', 'conversation.project'])
            ->whereHas('conversation', function ($q) use ($client) {
                $q->where('conversable_type', \App\Models\Client::class)
                  ->where('conversable_id', $client->id);
            })
            ->orderByDesc('created_at');

        // Optional filters
        if ($type = $request->input('type')) {
            $query->where('type', $type);
        }
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }
        if ($search = $request->input('search')) {
            $query->where(function ($qq) use ($search) {
                $qq->where('subject', 'like', "%{$search}%")
                   ->orWhere('body', 'like', "%{$search}%");
            });
        }

        $emails = $query->paginate($perPage, ['*'], 'page', $page);

        return response()->json($emails);
    }

    /**
     * Return client details with combined emails and presentations (including historical from linked lead).
     */
    public function details(Client $client)
    {
        $user = Auth::user();

        // Permission check (mirrors show/getEmail)
        if ($user->hasPermission('view_clients')) {
            // ok
        } elseif ($user->isContractor()) {
            if (!$user->clients->contains('id', $client->id)) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
        } else {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $client->load('lead');
        $lead = $client->lead;

        // Presentations (client + from linked lead)
        $presentations = [];
        foreach ($client->presentations()->withCount('slides')->orderByDesc('id')->get() as $p) {
            $presentations[] = [
                'id' => $p->id,
                'title' => $p->title,
                'type' => $p->type,
                'is_template' => (bool)$p->is_template,
                'slides_count' => $p->slides_count,
                'share_token' => $p->share_token,
                'source' => 'client',
            ];
        }
        if ($lead) {
            foreach ($lead->presentations()->withCount('slides')->orderByDesc('id')->get() as $p) {
                $presentations[] = [
                    'id' => $p->id,
                    'title' => $p->title,
                    'type' => $p->type,
                    'is_template' => (bool)$p->is_template,
                    'slides_count' => $p->slides_count,
                    'share_token' => $p->share_token,
                    'source' => 'lead',
                ];
            }
        }

        // Emails via conversations for client and (optionally) lead
        $conversationsQuery = Conversation::with(['emails' => function ($q) {
            $q->orderByDesc('created_at');
        }])->where(function ($q) use ($client, $lead) {
            $q->where('conversable_type', Client::class)
              ->where('conversable_id', $client->id);
            if ($lead) {
                $q->orWhere(function ($qq) use ($lead) {
                    $qq->where('conversable_type', Lead::class)
                       ->where('conversable_id', $lead->id);
                });
            }
        });

        $conversations = $conversationsQuery->orderByDesc('last_activity_at')->get();
        $emails = [];
        foreach ($conversations as $conv) {
            foreach ($conv->emails as $em) {
                $emails[] = [
                    'id' => $em->id,
                    'conversation_id' => $conv->id,
                    'subject' => $em->subject,
                    'status' => $em->status,
                    'type' => $em->type,
                    'sent_at' => $em->sent_at,
                    'created_at' => $em->created_at,
                ];
            }
        }
        // Sort emails by created_at desc
        usort($emails, function ($a, $b) {
            return strtotime($b['created_at']) <=> strtotime($a['created_at']);
        });

        return response()->json([
            'client' => (new ClientResource($client))->resolve(request()),
            'lead' => $lead ? [
                'id' => $lead->id,
                'first_name' => $lead->first_name,
                'last_name' => $lead->last_name,
                'email' => $lead->email,
                'status' => $lead->status,
            ] : null,
            'presentations' => $presentations,
            'emails' => $emails,
        ]);
    }
}
