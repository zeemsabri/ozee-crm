<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class OptionsController extends Controller
{
    /**
     * Return options by key from config/options.php
     */
    public function show(string $key)
    {
        $options = config('options.' . $key);
        if ($options === null) {
            return response()->json([
                'message' => 'Option set not found.'
            ], 404);
        }
        return response()->json($options);
    }
}
