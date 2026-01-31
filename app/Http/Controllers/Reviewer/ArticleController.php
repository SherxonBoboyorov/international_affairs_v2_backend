<?php

namespace App\Http\Controllers\Reviewer;

use App\Http\Controllers\Controller;
use App\Models\ArticleReviewerAssignment;
use App\Models\ReviewCriteria;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class ArticleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = ArticleReviewerAssignment::with(['article', 'article.originalArticle'])
            ->where('reviewer_id', Auth::id())
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
        ->where('reviewer_id', Auth::id())
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

        $assignment = ArticleReviewerAssignment::where('reviewer_id', Auth::id())
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
            ->where('reviewer_id', Auth::id())
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
                'deadline' => $assignment->deadline,
                'status' => $assignment->status,
                'assigned_at' => $assignment->assigned_at
            ];
        });

        return response()->json([
            'status' => true,
            'data' => $assignments
        ]);
    }


    public function submitReview(Request $request, $id): JsonResponse
    {
        $reviewCriteria = ReviewCriteria::active()->get();

        $rules = [
            'general_recommendation' => 'required|in:accept,after_revision,reject',
            'review_comments' => 'required|string|max:5000',
            'review_files' => 'nullable|array',
            'review_files.*' => 'file|mimes:pdf,doc,docx|max:10240',
        ];

        foreach ($reviewCriteria as $criterion) {
            $field_name = $criterion->id;
            $rules[$field_name] = 'required|numeric|min:0|max:' . $criterion->max_score;
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $assignment = ArticleReviewerAssignment::where('reviewer_id', Auth::id())
            ->where('id', $id)
            ->firstOrFail();

        if ($assignment->status !== 'in_progress') {
            return response()->json([
                'status' => false,
                'message' => 'Faqat ish jarayonidagi maqolani tugatish mumkin'
            ], 422);
        }

        $reviewFiles = [];
        if ($request->hasFile('review_files')) {
            foreach ($request->file('review_files') as $file) {
                $filePath = $file->store('review_files', 'public');
                $reviewFiles[] = $filePath;
            }
        }

        $criteriaScores = [];
        foreach ($reviewCriteria as $criterion) {
            $field_name = $criterion->id;
            $criteriaScores[$criterion->id] = $request->input($field_name);
        }

        $assignment->update([
            'status' => 'completed',
            'completed_at' => now(),
            'general_recommendation' => $request->general_recommendation,
            'review_comments' => $request->review_comments,
            'review_files' => $reviewFiles,
            'criteria_scores' => $criteriaScores,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'successfully submitted review',
            'data' => $assignment->load(['article'])
        ]);
    }

    private function getCompletedReviewData($assignment, $reviewCriteria): array
    {
        $scores = [];
        $savedScores = $assignment->criteria_scores ?? [];

        foreach ($reviewCriteria as $criterion) {
            $scores[] = [
                'id' => $criterion->id,
                'name' => $criterion->name_ru,
                'name_uz' => $criterion->name_uz,
                'name_en' => $criterion->name_en,
                'localized_name' => $criterion->localized_name,
                'max_score' => $criterion->max_score,
                'score' => $savedScores[$criterion->id] ?? null,
                'star_count' => $criterion->max_score,
            ];
        }

        return [
            'scores' => $scores,
            'general_recommendation' => $assignment->general_recommendation,
            'review_comments' => $assignment->review_comments,
            'review_files' => $assignment->review_files ?
                collect($assignment->review_files)->map(function ($file) {
                    return [
                        'path' => $file,
                        'url' =>  $file,
                        'name' => basename($file)
                    ];
                }) : [],
            'completed_at' => $assignment->completed_at,
        ];
    }

    public function completed(Request $request): JsonResponse
    {
        $query = ArticleReviewerAssignment::with(['article', 'article.originalArticle'])
            ->where('reviewer_id', Auth::id())
            ->where('status', 'completed')
            ->orderBy('completed_at', 'desc');

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
                'completed_at' => $assignment->completed_at
            ];
        });

        return response()->json([
            'status' => true,
            'data' => $assignments
        ]);
    }
}
