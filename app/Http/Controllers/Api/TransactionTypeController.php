<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TransactionType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class TransactionTypeController extends Controller
{
    public function index()
    {
        return response()->json(TransactionType::orderBy('name')->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:transaction_types,name',
        ]);
        $transactionType = TransactionType::create([
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']),
            'created_by_user_id' => Auth::id(),
        ]);

        return response()->json($transactionType, 201);
    }

    public function search(Request $request)
    {
        $query = $request->get('query');
        $results = TransactionType::query()
            ->when($query, function ($q) use ($query) {
                $q->where('name', 'like', "%$query%");
            })
            ->orderBy('name')
            ->get();

        return response()->json($results);
    }
}
