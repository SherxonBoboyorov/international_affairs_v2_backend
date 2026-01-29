<?php

namespace App\Http\Controllers\Reviewer;

use App\Http\Controllers\Controller;
use App\Models\ArticleReviewerAssignment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ArticleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = ArticleReviewerAssignment::with(['article', 'article.originalArticle'])
            ->where('reviewer_id', auth()->id())
            ->where('status', 'assigned')
            ->orderBy('assigned_at', 'desc');

        if ($request->has('created_at') && $request->created_at) {
            $query->whereDate('created_at', $request->created_at);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->whereHas('article', function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('fio', 'like', "%{$search}%");
            });
        }

        $perPage = $request->get('per_page', 10);
        $assignments = $query->paginate($perPage);

        $assignments->getCollection()->transform(function ($assignment) {
            return [
                'id' => $assignment->id,
                'article_id' => $assignment->article->id,
                'title' => $assignment->article->title,
                'deadline' => $assignment->deadline,
                'status' => $assignment->status,
                'assigned_at' => $assignment->assigned_at,
            ];
        });

        return response()->json([
            'status' => true,
            'data' => $assignments
        ]);
    }

    public function show($id): JsonResponse
    {
        $assignment = ArticleReviewerAssignment::with([
            'article',
            'article.originalArticle',
            'article.creator'
        ])
        ->where('reviewer_id', auth()->id())
        ->where('id', $id)
        ->firstOrFail();

        $activeFilePath = $assignment->article->getActiveFilePath();
        $activeFileUrl = $activeFilePath ? $activeFilePath : null;

        return response()->json([
            'status' => true,
            'data' => [
                'id' => $assignment->id,
                'article_id' => $assignment->article->id,
                'title' => $assignment->article->title,
                'description' => $assignment->article->description,
                'file_path' => $activeFileUrl,
                'assigned_at' => $assignment->assigned_at,
                'deadline' => $assignment->deadline,
                ],
        ]);
    }

    public function updateStatus(Request $request, $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'action' => 'required|in:accept,reject',
            'comment' => 'required_if:action,reject|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $assignment = ArticleReviewerAssignment::where('reviewer_id', auth()->id())
            ->where('id', $id)
            ->firstOrFail();

        if ($assignment->status !== 'assigned') {
            return response()->json([
                'status' => false,
                'message' => 'Faqat tayinlangan maqolani qabul qilish yoki rad etish mumkin'
            ], 422);
        }

        if ($request->action === 'accept') {
            $newStatus = 'in_progress';
            $message = 'Maqola qabul qilindi va ish boshlandi';
            $comment = null;
        } else {
            $newStatus = 'refused';
            $message = 'Maqola rad etildi';
            $comment = $request->comment;
        }

        $assignment->update([
            'status' => $newStatus,
            'comment' => $comment,
        ]);

        return response()->json([
            'status' => true,
            'message' => $message,
            'data' => $assignment->load(['article'])
        ]);
    }

    public function inProgress(Request $request): JsonResponse
    {
        $query = ArticleReviewerAssignment::with(['article', 'article.originalArticle'])
            ->where('reviewer_id', auth()->id())
            ->where('status', 'in_progress')
            ->orderBy('assigned_at', 'desc');

        if ($request->has('created_at') && $request->created_at) {
            $query->whereDate('created_at', $request->created_at);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->whereHas('article', function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                ->orWhere('fio', 'like', "%{$search}%");
            });
        }

        $perPage = $request->get('per_page', 10);
        $assignments = $query->paginate($perPage);

        $assignments->getCollection()->transform(function ($assignment) {
            return [
                'id' => $assignment->id,
                'article_id' => $assignment->article->id,
                'title' => $assignment->article->title,
                'author' => $assignment->article->fio,
                'deadline' => $assignment->deadline,
                'status' => $assignment->status,
                'status_name' => $assignment->status_name,
                'assigned_at' => $assignment->assigned_at,
                'is_overdue' => $assignment->is_overdue,
                'days_until_deadline' => $assignment->deadline ?
                    now()->diffInDays($assignment->deadline, false) : null,
                'time_in_progress' => $assignment->assigned_at ?
                    now()->diffInDays($assignment->assigned_at) : null,
            ];
        });

        return response()->json([
            'status' => true,
            'data' => $assignments
        ]);
    }
}
