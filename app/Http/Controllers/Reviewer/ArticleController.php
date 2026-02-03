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

    public function inProgressShow($id): JsonResponse
    {
        $assignment = ArticleReviewerAssignment::with([
            'article',
            'article.originalArticle',
            'article.creator'
        ])
        ->where('reviewer_id', Auth::id())
        ->where('id', $id)
        ->where('status', 'in_progress')
        ->firstOrFail();

        $reviewCriteria = ReviewCriteria::active()->get();

        $activeFilePath = $assignment->article->getActiveFilePath();
        $activeFileUrl = $activeFilePath ?  $activeFilePath : null;

        $savedScoresDraft = [];
        if ($assignment->has_valid_draft && $assignment->draft_criteria_scores) {
            if (is_string($assignment->draft_criteria_scores)) {
                $savedScoresDraft = json_decode($assignment->draft_criteria_scores, true) ?? [];
            } elseif (is_array($assignment->draft_criteria_scores)) {
                $savedScoresDraft = $assignment->draft_criteria_scores;
            }
        }

        $draftData = null;
        if ($assignment->has_valid_draft) {
            $draftData = [
                'general_recommendation' => $assignment->draft_general_recommendation,
                'review_comments' => $assignment->draft_review_comments,
                'draft_files' => $assignment->draft_review_files,
                'expires_at' => $assignment->draft_expires_at,
                'last_saved_at' => $assignment->draft_last_saved_at,
                'deadline_at' => $assignment->deadline,
            ];
        }

        return response()->json([
            'status' => true,
            'data' => [
                'id' => $assignment->id,
                'article_id' => $assignment->article->id,
                'title' => $assignment->article->title,
                'assigned_at' => $assignment->assigned_at,
                'deadline' => $assignment->deadline,
                'status' => $assignment->status,

                'review_criteria' => $reviewCriteria->map(function ($criterion) use ($savedScoresDraft) {
                    return [
                        'id' => $criterion->id,
                        'name_ru' => $criterion->name_ru,
                        'name_uz' => $criterion->name_uz,
                        'name_en' => $criterion->name_en,
                        'max_score' => $criterion->max_score,
                        'score' => $savedScoresDraft[$criterion->id] ?? null,
                    ];
                }),

                'has_draft' => $assignment->has_valid_draft,
                'draft' => $draftData,
            ],
        ]);
    }

    public function submitReview(Request $request, $id): JsonResponse
    {
        $reviewCriteria = ReviewCriteria::active()->get();

        $rules = [
            'general_recommendation' => 'required|in:accept,after_revision,reject',
        ];

        foreach ($reviewCriteria as $criterion) {
            $field_name = $criterion->id;
            $rules[$field_name] = 'nullable|numeric|min:0|max:' . $criterion->max_score;
        }

        $rules['review_comments'] = 'nullable|string|max:5000';
        $rules['review_files'] = 'nullable|array';
        $rules['review_files.*'] = 'file|mimes:pdf,doc,docx|max:10240';

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
                $originalName = $file->getClientOriginalName();

                $filePath = $file->storeAs('review_files', $originalName, 'public');

                $reviewFiles[] = [
                    'path' => $filePath,
                    'original_name' => $originalName,
                ];
            }
        }

        $criteriaScores = [];
        foreach ($reviewCriteria as $criterion) {
            $field_name = $criterion->id;
            $criteriaScores[$criterion->id] = $request->input($field_name);
        }

        $updateData = [
            'status' => 'completed',
            'completed_at' => now(),
            'general_recommendation' => $request->general_recommendation,
            'criteria_scores' => $criteriaScores,
            'review_mode' => 'criteria_based',
        ];

        if ($request->has('review_comments') && !empty($request->review_comments)) {
            $updateData['review_comments'] = $request->review_comments;
        }

        if (!empty($reviewFiles)) {
            $updateData['review_files'] = $reviewFiles;
        } else {
            $updateData['review_files'] = null;
        }

        $assignment->update($updateData);
        $assignment->clearDraft();

        return response()->json([
            'status' => true,
            'message' => 'Review successfully submitted',
            'data' => [
                'assignment' => $assignment->load(['article']),
                'review_mode' => 'criteria_based',
                'submitted_data' => [
                    'has_comments' => !empty($request->review_comments),
                    'has_files' => !empty($reviewFiles),
                    'files_count' => count($reviewFiles),
                    'criteria_count' => count($criteriaScores),
                    'files' => $reviewFiles,
                ]
            ]
        ]);
    }

    public function completed(Request $request): JsonResponse
    {
        $query = ArticleReviewerAssignment::with(['article', 'article.originalArticle'])
            ->where('reviewer_id', Auth::id())
            ->whereIn('status', ['completed', 'overdue'])
            ->orderBy('assigned_at', 'desc');

        if ($request->has('status_filter') && $request->status_filter !== 'all') {
            if (in_array($request->status_filter, ['completed', 'overdue'])) {
                $query->where('status', $request->status_filter);
            }
        }

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
            'data' => $assignments,
        ]);
    }

    public function completedShow($id): JsonResponse
    {
        $assignment = ArticleReviewerAssignment::with([
            'article',
            'article.originalArticle',
            'article.creator'
        ])
        ->where('reviewer_id', Auth::id())
        ->where('id', $id)
        ->whereIn('status', ['completed', 'overdue'])
        ->firstOrFail();

        $activeFilePath = $assignment->article->getActiveFilePath();
        $activeFileUrl = $activeFilePath ? $activeFilePath : null;

        $showCompletedReview = $assignment->status === 'completed';
        $showOverdueInfo = $assignment->status === 'overdue';

        return response()->json([
            'status' => true,
            'data' => [
                'id' => $assignment->id,
                'article_id' => $assignment->article->id,
                'title' => $assignment->article->title,
                'description' => $assignment->article->description,
                'assigned_at' => $assignment->assigned_at,
                'deadline' => $assignment->deadline,
                'status' => $assignment->status,
                'status_name' => $assignment->status_name,
                'completed_at' => $assignment->completed_at,
                'is_overdue' => $assignment->is_overdue,
                'completed_review' => $showCompletedReview ? $this->getCompletedReviewData($assignment) : null,
                'overdue_info' => $showOverdueInfo ? [
                    'original_deadline' => $assignment->deadline,
                    'status_reason' => $assignment->comment ?? 'Deadline o\'tgan',
                ] : null,
                'has_draft' => $assignment->has_draft,
                'draft_info' => $assignment->has_draft ? [
                    'expires_at' => $assignment->draft_expires_at,
                    'expires_in_hours' => $assignment->draft_expires_at,
                    'last_saved_at' => $assignment->draft_last_saved_at,
                    'has_scores' => !empty($assignment->draft_criteria_scores),
                    'has_recommendation' => !empty($assignment->draft_general_recommendation),
                    'has_comments' => !empty($assignment->draft_review_comments),
                ] : null,
            ],
        ]);
    }

    private function getCompletedReviewData($assignment): array
    {
        $reviewCriteria = ReviewCriteria::active()->get();
        $savedScores = $assignment->criteria_scores ?? [];

        $scores = [];
        foreach ($reviewCriteria as $criterion) {
            $scores[] = [
                'id' => $criterion->id,
                'name_ru' => $criterion->name_ru,
                'name_uz' => $criterion->name_uz,
                'name_en' => $criterion->name_en,
                'max_score' => $criterion->max_score,
                'score' => $savedScores[$criterion->id] ?? null,
            ];
        }

        return [
            'scores' => $scores,
            'general_recommendation' => $assignment->general_recommendation,
            'review_comments' => $assignment->review_comments,
            'review_files' => $assignment->review_files,
            'action_history' => [
                'start_review' => $assignment->assigned_at,
                'save_draft' => $assignment->draft_last_saved_at,
                'completed_at' => $assignment->completed_at,
            ]
        ];

    }

    public function saveDraft(Request $request, $id): JsonResponse
    {
        $assignment = ArticleReviewerAssignment::where('reviewer_id', Auth::id())
            ->where('id', $id)
            ->where('status', 'in_progress')
            ->firstOrFail();

        $reviewCriteria = ReviewCriteria::active()->get();

        $rules = [];
        foreach ($reviewCriteria as $criterion) {
            $field_name = $criterion->id;
            $rules[$field_name] = 'nullable|numeric|min:0|max:' . $criterion->max_score;
        }

        $rules['general_recommendation'] = 'nullable|in:accept,after_revision,reject';
        $rules['review_comments'] = 'nullable|string|max:5000';
        $rules['draft_files'] = 'nullable|array';
        $rules['draft_files.*'] = 'file|mimes:pdf,doc,docx|max:10240';

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $criteriaScores = [];
        foreach ($reviewCriteria as $criterion) {
            $field_name = $criterion->id;
            if ($request->has($field_name) && $request->input($field_name) !== null) {
                $criteriaScores[$criterion->id] = $request->input($field_name);
            }
        }

        $draftFiles = [];
        if ($request->hasFile('draft_files')) {
            foreach ($request->file('draft_files') as $file) {
                $originalName = $file->getClientOriginalName();


                $filePath = $file->storeAs('draft_review_files', $originalName, 'public');

                $draftFiles[] = [
                    'path' => $filePath,
                    'original_name' => $originalName,
                ];
            }
        }

        $existingFiles = [];
        if ($assignment->draft_review_files) {
            if (is_string($assignment->draft_review_files)) {
                $existingFiles = json_decode($assignment->draft_review_files, true) ?? [];
            } elseif (is_array($assignment->draft_review_files)) {
                $existingFiles = $assignment->draft_review_files;
            }
        }

        if (!empty($draftFiles)) {
            $allDraftFiles = array_merge($existingFiles, $draftFiles);
        } else {
            $allDraftFiles = $existingFiles;
        }

        $expiresAt = $assignment->deadline;
        if ($expiresAt && $expiresAt < now()) {
            $expiresAt = now()->addHour();
        }

        $assignment->update([
            'draft_criteria_scores' => !empty($criteriaScores) ? $criteriaScores : null,
            'draft_general_recommendation' => $request->input('general_recommendation'),
            'draft_review_comments' => $request->input('review_comments'),
            'draft_review_files' => !empty($allDraftFiles) ? $allDraftFiles : null,
            'draft_expires_at' => $expiresAt,
            'draft_last_saved_at' => now(),
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Черновик сохранен',
            'data' => [
                'assignment_id' => $assignment->id,
                'saved_scores' => $criteriaScores,
                'saved_recommendation' => $request->input('general_recommendation'),
                'saved_comments' => $request->input('review_comments'),
                'saved_files' => $allDraftFiles,
                'new_files_count' => count($draftFiles),
                'total_files_count' => count($allDraftFiles),
                'expires_at' => $assignment->draft_expires_at,
                'last_saved_at' => $assignment->draft_last_saved_at,
            ]
        ]);
    }

    public function loadDraft($id): JsonResponse
    {
        $assignment = ArticleReviewerAssignment::where('reviewer_id', Auth::id())
            ->where('id', $id)
            ->where('status', 'in_progress')
            ->firstOrFail();

        if (!$assignment->has_draft) {
            return response()->json([
                'status' => false,
                'message' => 'Сохраненный черновик не найден'
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data' => [
                'assignment_id' => $assignment->id,
                'criteria_scores' => $assignment->draft_criteria_scores,
                'general_recommendation' => $assignment->draft_general_recommendation,
                'review_comments' => $assignment->draft_review_comments,
                'expires_at' => $assignment->draft_expires_at,
                'expires_in_hours' => $assignment->draft_expires_at,
                'last_saved_at' => $assignment->draft_last_saved_at,
            ]
        ]);
    }

    public function deleteDraft($id): JsonResponse
    {
        $assignment = ArticleReviewerAssignment::where('reviewer_id', Auth::id())
            ->where('id', $id)
            ->firstOrFail();

        $assignment->clearDraft();

        return response()->json([
            'status' => true,
            'message' => 'Черновик удален'
        ]);
    }
}
