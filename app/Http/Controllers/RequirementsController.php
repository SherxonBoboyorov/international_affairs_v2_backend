<?php

namespace App\Http\Controllers;

use App\Models\ScientificActivity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RequirementsController extends Controller
{
    public function scientificActivity(): JsonResponse
    {
        $scientificActivity = ScientificActivity::orderByDesc('created_at')->get();

        return response()->json([
            'status' => true,
            'data' => $scientificActivity
        ]);
    }
}
