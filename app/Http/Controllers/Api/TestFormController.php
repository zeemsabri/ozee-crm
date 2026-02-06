<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TestFormController extends Controller
{
    /**
     * Handle the test form submission.
     *
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        // Validate the request
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
        ]);

        // Return validation errors if validation fails
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Process the form data
        // In a real application, you might save to database, etc.
        $data = $request->all();

        // Return a success response
        return response()->json([
            'message' => 'Form submitted successfully',
            'data' => $data,
        ], 200);
    }
}
