<?php

namespace App\Http\Controllers\Reviewer;

use App\Http\Controllers\Controller;
use App\Models\ArticleReviewerAssignment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class ArticleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = ArticleReviewerAssignment::with(['article', 'article.originalArticle'])
            ->where('reviewer_id', auth()->id())
            ->orderBy('assigned_at', 'desc');

        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
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
                'assigned_at' => $assignment->assigned_at,
                'is_overdue' => $assignment->is_overdue,
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
                'article' => [
                    'id' => $assignment->article->id,
                    'title' => $assignment->article->title,
                    'description' => $assignment->article->description,
                    'file_path' => $activeFileUrl,
                    'assigned_at' => $assignment->assigned_at,
                    'deadline' => $assignment->deadline,
                ],
            ]
        ]);
    }

    public function updateStatus(Request $request, $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:assigned,in_progress,overdue,completed',
            'comment' => 'nullable|string|max:500',
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

        if ($request->status === 'assigned' && $assignment->status === 'assigned') {
            $newStatus = 'in_progress';
            $message = 'Maqola qabul qilindi va ish boshlandi';
        } else {
            $newStatus = $request->status;
            $message = 'Status muvaffaqiyatli yangilandi';
        }

        $assignment->update([
            'status' => $newStatus,
            'comment' => $request->comment,
        ]);

        if ($newStatus === 'completed') {
            $assignment->update(['completed_at' => now()]);
        }

        return response()->json([
            'status' => true,
            'message' => $message,
            'data' => $assignment->load(['article'])
        ]);
    }

    public function submitReview(Request $request, $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'originality_score' => 'required|numeric|min:0|max:10',
            'methodology_score' => 'required|numeric|min:0|max:10',
            'argumentation_score' => 'required|numeric|min:0|max:10',
            'structure_score' => 'required|numeric|min:0|max:10',
            'significance_score' => 'required|numeric|min:0|max:10',
            'general_recommendation' => 'required|in:accept,minor_revisions,major_revisions,reject',
            'review_comments' => 'required|string|max:5000',
            'review_files' => 'nullable|array',
            'review_files.*' => 'file|mimes:pdf,doc,docx|max:10240',
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

        $reviewFiles = [];
        if ($request->hasFile('review_files')) {
            foreach ($request->file('review_files') as $file) {
                $filePath = $file->store('review_files', 'public');
                $reviewFiles[] = $filePath;
            }
        }

        $assignment->update([
            'status' => 'completed',
            'completed_at' => now(),
            'originality_score' => $request->originality_score,
            'methodology_score' => $request->methodology_score,
            'argumentation_score' => $request->argumentation_score,
            'structure_score' => $request->structure_score,
            'significance_score' => $request->significance_score,
            'general_recommendation' => $request->general_recommendation,
            'review_comments' => $request->review_comments,
            'review_files' => $reviewFiles,
        ]);

        return response()->json([
            'status' => true,
            'data' => $assignment->load(['article'])
        ]);
    }
}
