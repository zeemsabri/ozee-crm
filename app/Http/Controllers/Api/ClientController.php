<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ClientResource;
use App\Models\Client;
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
}
