<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ValueDictionaryRegistry;

class ValueDictionaryController extends Controller
{
    public function index(ValueDictionaryRegistry $registry)
    {
        return response()->json($registry->all());
    }

    public function show(string $model, string $field, ValueDictionaryRegistry $registry)
    {
        $data = $registry->for($model, $field);
        if (! $data) {
            return response()->json([], 404);
        }

        return response()->json($data);
    }
}
