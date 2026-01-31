<?php

namespace App\Http\Controllers;

use App\Models\ArticleReviewer;
use App\Models\ReviewCriteria;
use App\Models\ScientificActivity;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

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

    public function reviewerRequirements(): JsonResponse
    {
        $role = Role::where('name', 'reviewer')
            ->with(['users' => function ($q) {
                $q->where('active', 1)
                ->select('users.id', 'users.name');
            }])->first();

        return response()->json([
            'status' => true,
            'data' => $role ? $role->users : []
        ]);
    }

    public function reviewCriteria(): JsonResponse
    {
        $criteria = ReviewCriteria::orderBy('sort_order')->get();

        return response()->json([
            'status' => true,
            'data' => $criteria
        ]);
    }
}
