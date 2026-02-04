<?php

namespace App\Http\Controllers\Editor;

use App\Http\Controllers\Controller;
use App\Models\ArticleConsideration;
use App\Models\ArticleReviewer;
use App\Models\ReviewCriteria;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ArticleReviewersController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->get('per_page', 10);
        $search  = $request->get('search');
        $status  = $request->get('status', 'all');
        $page = (int) $request->get('page', 1);

        $externalArticles = collect();

        if ($status === 'all' || $status === 'not_assigned') {
            $externalQuery = ArticleConsideration::query()
                ->where('status', 'not_assigned');

            if ($search) {
                $externalQuery->where(function ($q) use ($search) {
                    $q->where('article_title', 'like', "%{$search}%")
                    ->orWhere('fio', 'like', "%{$search}%");
                });
            }

            $externalArticles = $externalQuery->get()->map(function ($article) {
                return [
                    'id' => $article->id,
                    'title' => $article->article_title,
                    'fio' => $article->fio,
                    'deadline' => null,
                    'status' => $article->status,
                    'created_at' => $article->created_at,
                    'type' => 'external',
                ];
            });
        }

        $reviewerQuery = ArticleReviewer::with(['originalArticle', 'creator']);

        if ($status !== 'all') {
            $reviewerQuery->where('status', $status);
        }

        if ($search) {
            $reviewerQuery->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                ->orWhere('fio', 'like', "%{$search}%");
            });
        }

        $reviewerArticles = $reviewerQuery->get()->map(function ($article) {
            return [
                'id' => $article->id,
                'title' => $article->title,
                'fio' => $article->fio,
                'deadline' => $article->deadline,
                'status' => $article->status,
                'created_at' => $article->created_at,
                'type' => 'reviewer',
                'source' => 'article_reviewers',
            ];
        });

        $allArticles = $externalArticles
            ->concat($reviewerArticles)
            ->sortByDesc('created_at')
            ->values();

        $total = $allArticles->count();
        $items = $allArticles->slice(($page - 1) * $perPage, $perPage)->values();

        $paginator = new LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $page,
            [
                'path'  => $request->url(),
                'query' => $request->query(),
            ]
        );

        return response()->json([
            'status' => true,
            'data' => $paginator,
        ]);
    }
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:500',
            'fio' => 'required|string|max:255',
            'file' => 'required|file|mimes:pdf,doc,docx|max:10240',
            'edited_file' => 'nullable|file|mimes:pdf,doc,docx|max:10240',
            'deadline' => 'nullable|date|after:today',
            'description' => 'nullable|string|max:2000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $filePath = null;
        $editedFilePath = null;

        if ($request->hasFile('file')) {
            $file = $request->file('file');

            $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
            $extension = $file->getClientOriginalExtension();

            $safeName = Str::slug($originalName);
            if (!$safeName) {
                $safeName = 'file';
            }

            $finalName = $safeName . '_' . time() . '.' . $extension;

            $filePath = $file->storeAs('article_reviewers', $finalName, 'public');
        }

        if ($request->hasFile('edited_file')) {
            $file = $request->file('edited_file');

            $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
            $extension = $file->getClientOriginalExtension();

            $safeName = Str::slug($originalName);
            if (!$safeName) {
                $safeName = 'edited-file';
            }

            $finalName = $safeName . '_' . time() . '.' . $extension;

            $editedFilePath = $file->storeAs('article_reviewers/edited', $finalName, 'public');
        }

        $article = ArticleReviewer::create([
            'title' => $request->title,
            'fio' => $request->fio,
            'file_path' => $filePath,
            'edited_file_path' => $editedFilePath,
            'deadline' => $request->deadline,
            'description' => $request->description,
            'status' => 'not_assigned',
            'created_by' => Auth::id(),
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Maqola muvaffaqiyatli qo\'shildi',
            'data' => $article
        ]);
    }

    public function convertToReviewer(Request $request, $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:500',
            'fio' => 'required|string|max:255',
            'edited_file' => 'required|file|mimes:pdf,doc,docx',
            'deadline' => 'nullable|date',
            'description' => 'nullable|string|max:2000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $article = ArticleConsideration::findOrFail($id);

        $editedFilePath = null;
        $editedFileName = null;

        if ($request->hasFile('edited_file')) {
            $file = $request->file('edited_file');

            $originalName = $file->getClientOriginalName();
            $extension = $file->getClientOriginalExtension();
            $nameWithoutExt = pathinfo($originalName, PATHINFO_FILENAME);

            $cleanName = preg_replace('/[^A-Za-z0-9.\-_]/', '_', $nameWithoutExt);
            if (!$cleanName) {
                $cleanName = 'edited-file';
            }

            $timestamp = now()->format('YmdHis');
            $userId = Auth::id();
            $random = Str::random(6);
            $finalName = "converted_{$timestamp}_user{$userId}_{$random}_{$cleanName}.{$extension}";

            $editedFilePath = $file->storeAs('article_reviewers/edited', $finalName, 'public');
            $editedFileName = $originalName;
        }

        $originalCreatedAt = $article->created_at;

        $reviewerArticle = ArticleReviewer::create([
            'title' => $request->title,
            'fio' => $request->fio,
            'file_path' => $article->article_file,
            'edited_file_path' => $editedFilePath,
            'deadline' => $request->deadline,
            'description' => $request->description,
            'status' => 'not_assigned',
            'created_by' => Auth::id(),
            'created_at' => $originalCreatedAt,
            'updated_at' => now(),
        ]);

        $article->update(['status' => 'converted']);

        return response()->json([
            'status' => true,
            'message' => 'Maqola muvaffaqiyatli tahrirlandi va saqlandi',
            'data' => [
                'id' => $reviewerArticle->id,
                'title' => $reviewerArticle->title,
                'fio' => $reviewerArticle->fio,
                'file_path' => $reviewerArticle->file_path ? $reviewerArticle->file_path : null,
                'edited_file_path' => $editedFilePath ? $editedFilePath : null,
                'edited_file_name' => $editedFileName,
                'original_file_name' => $editedFileName,
                'stored_file_name' => basename($editedFilePath),
                'deadline' => $reviewerArticle->deadline,
                'status' => $reviewerArticle->status,
                'type' => 'internal',
                'created_at' => $originalCreatedAt,
                'updated_at' => $reviewerArticle->updated_at,
                'conversion_date' => now(),
            ]
        ]);
    }
    public function updateEditedFile(Request $request, $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'edited_file' => 'required|file|mimes:pdf,doc,docx|max:10240',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $reviewerArticle = ArticleReviewer::findOrFail($id);

        if (
            $reviewerArticle->edited_file_path &&
            Storage::disk('public')->exists($reviewerArticle->edited_file_path)
        ) {
            Storage::disk('public')->delete($reviewerArticle->edited_file_path);
        }

        $file = $request->file('edited_file');

        $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $extension = $file->getClientOriginalExtension();

        $safeName = Str::slug($originalName);
        if (!$safeName) {
            $safeName = 'edited-file';
        }

        $finalName = $safeName . '_' . time() . '.' . $extension;

        $editedFilePath = $file->storeAs(
            'article_reviewers/edited',
            $finalName,
            'public'
        );

        $reviewerArticle->update([
            'edited_file_path' => $editedFilePath,
        ]);

        return response()->json([
            'status' => true,
            'data' => [
                'id' => $reviewerArticle->id,
                'edited_file_path' =>  $reviewerArticle->edited_file_path,
                'updated_at' => $reviewerArticle->updated_at,
            ]
        ]);
    }
    public function sendToReviewers(Request $request, $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'reviewers' => 'required|array',
            'reviewers.*' => 'exists:users,id',
            'deadline' => 'nullable|date|after:today',
            'description' => 'nullable|string|max:2000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $article = null;
        $source = null;

        $article = ArticleReviewer::find($id);
        if ($article) {
            $source = 'reviewer';
        } else {
            $article = ArticleConsideration::find($id);
            if ($article) {
                $source = 'external';
            }
        }

        if ($source === 'external') {
            $filePath = null;
            if ($article->article_file) {
                $newFileName = 'converted_' . time() . '_' . basename($article->article_file);
                $newPath = 'article_reviewers/' . $newFileName;

                if (Storage::disk('public')->exists($article->article_file)) {
                    Storage::disk('public')->copy($article->article_file, $newPath);
                    $filePath = $newPath;
                }
            }

            $reviewerArticle = ArticleReviewer::create([
                'title' => $article->article_title,
                'fio' => $article->fio,
                'file_path' => $filePath,
                'deadline' => $request->deadline,
                'description' => $request->description,
                'status' => 'assigned',
                'created_by' => Auth::id(),
                'original_article_id' => $article->id,
                'original_fio' => $article->fio,
                'original_article_file' => $article->article_file,
                'original_title' => $article->article_title,
            ]);

            $reviewerData = [];
            foreach ($request->reviewers as $index => $reviewerId) {
                $reviewerData[$reviewerId] = [
                    'assigned_at' => now(),
                    'deadline' => $request->reviewer_deadlines[$index] ?? $request->deadline,
                    'status' => 'assigned',
                    'comment' => null,
                ];
            }

            $reviewerArticle->reviewers()->attach($reviewerData);
            $article->update(['status' => 'appointed']);

            return response()->json([
                'status' => true,
                'message' => 'Maqola reviewerlarga yuborildi',
                'data' => $reviewerArticle->load(['reviewers'])
            ]);

        } else {
            $article->update([
                'status' => 'assigned',
                'deadline' => $request->deadline,
                'description' => $request->description,
            ]);

            $reviewerData = [];
            foreach ($request->reviewers as $index => $reviewerId) {
                $reviewerData[$reviewerId] = [
                    'assigned_at' => now(),
                    'deadline' => $request->reviewer_deadlines[$index] ?? $request->deadline,
                    'status' => 'assigned',
                    'comment' => null,
                ];
            }

            $article->reviewers()->attach($reviewerData);

            $sentFilePath = $article->edited_file_path ?? $article->file_path;

            return response()->json([
                'status' => true,
                'data' => [
                    'article' => $article->load(['reviewers']),
                    'sent_file' => [
                        'path' => $sentFilePath,
                        'url' => 'https://international-affairs.uz/storage/' . $sentFilePath,
                        'type' => $article->edited_file_path ? 'edited' : 'original'
                    ]
                ]
            ]);
        }
    }

    public function show($id): JsonResponse
    {
        $article = ArticleReviewer::with(['originalArticle', 'assignments.reviewer'])->find($id);

        if ($article) {
            $activeFilePath = $article->getActiveFilePath();

            $assignments = $article->assignments->map(function ($assignment) {
                return [
                    'id' => $assignment->id,
                    'reviewer_id' => $assignment->reviewer->id,
                    'name' => $assignment->reviewer->name,
                    'status' => $assignment->status,
                    'status_name' => $assignment->status_name,
                    'assigned_at' => $assignment->assigned_at,
                    'general_recommendation' => $assignment->general_recommendation,
                ];
            });

            $assignmentsByStatus = [
                'assigned' => $assignments->where('status', 'assigned')->values(),
                'in_progress' => $assignments->where('status', 'in_progress')->values(),
                'overdue' => $assignments->where('status', 'overdue')->values(),
                'completed' => $assignments->where('status', 'completed')->values(),
            ];

            $summary = [
                'total_reviewers' => $assignments->count(),
                'assigned' => $assignments->where('status', 'assigned')->count(),
                'in_progress' => $assignments->where('status', 'in_progress')->count(),
                'overdue' => $assignments->where('status', 'overdue')->count(),
                'completed' => $assignments->where('status', 'completed')->count(),
                'pending_reviews' => $assignments->where('status', '!=', 'completed')->count(),
                'has_overdue' => $assignments->where('is_overdue', true)->count() > 0,
                'has_extended_deadlines' => $assignments->where('deadline_info.was_extended', true)->count() > 0,
            ];

            return response()->json([
                'status' => true,
                'data' => [
                    'id' => $article->id,
                    'article_title' => $article->title,
                    'authors_name' => $article->fio,
                    'file_path' => $article->file_path ? $article->file_path : null,
                    'file_name' => $activeFilePath ? basename($activeFilePath) : null,
                    'edited_file_path' => $article->edited_file_path ? $article->edited_file_path : null,
                    'edited_file_name' => $article->edited_file_path ? basename($article->edited_file_path) : null,
                    'deadline' => $article->deadline,
                    'status' => $article->status,
                    'type' => 'internal',
                    'assignments' => $assignments,
                ]
            ]);
        }

        $article = ArticleConsideration::find($id);

        if ($article) {
            return response()->json([
                'status' => true,
                'data' => [
                    'id' => $article->id,
                    'article_title' => $article->article_title,
                    'authors_name' => $article->fio,
                    'file_path' => 'https://international-affairs.uz/storage/' . $article->article_file,
                    'deadline' => null,
                    'status' => $article->status,
                    'status_name' => $article->status_name,
                    'type' => 'external',
                    'created_at' => $article->created_at,
                    'updated_at' => $article->updated_at,
                ]
            ]);
        }

        return response()->json([
            'status' => false,
            'message' => 'Maqola topilmadi'
        ], 404);
    }

    public function getReviewerReview($articleId, $reviewerId): JsonResponse
    {
        $article = ArticleReviewer::find($articleId);

        if (!$article) {
            return response()->json([
                'status' => false,
                'message' => 'Maqola topilmadi'
            ], 404);
        }

        $assignment = $article->assignments()
            ->with(['reviewer'])
            ->where('reviewer_id', $reviewerId)
            ->first();

        if (!$assignment) {
            return response()->json([
                'status' => false,
                'message' => 'Reviewer topilmadi'
            ], 404);
        }

        $reviewData = null;

        if ($assignment->status === 'completed') {
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

            $reviewFiles = [];
            if ($assignment->review_files) {
                if (is_string($assignment->review_files)) {
                    $files = json_decode($assignment->review_files, true) ?? [];
                } else {
                    $files = $assignment->review_files;
                }

                foreach ($files as $file) {
                    if (is_array($file)) {
                        $reviewFiles[] = [
                            'path' => $file['path'],
                            'name' => $file['original_name'] ?? null,
                        ];
                    } else {
                        $reviewFiles[] = [
                            'path' => $file,
                            'name' => $file->original_name
                        ];
                    }
                }
            }

            $draftFiles = [];
            if ($assignment->draft_review_files) {
                if (is_string($assignment->draft_review_files)) {
                    $draftFilesData = json_decode($assignment->draft_review_files, true) ?? [];
                } else {
                    $draftFilesData = $assignment->draft_review_files;
                }

                foreach ($draftFilesData as $file) {
                    if (is_array($file)) {
                        $draftFiles[] = [
                            'path' => $file['path'],
                            'name' => $file['original_name'] ?? null,
                        ];
                    } else {
                        $draftFiles[] = [
                            'path' => $file,
                            'name' => $file->original_name
                        ];
                    }
                }
            }

            $reviewData = [
                'scores' => $scores,
                'general_recommendation' => $assignment->general_recommendation,
                'review_comments' => $assignment->review_comments,
                'review_files' => $reviewFiles,
                'draft_files' => $draftFiles,
                'completed_at' => $assignment->completed_at,
            ];

        } elseif ($assignment->status === 'refused') {
            $reviewData = [
                'comment' => $assignment->comment,
            ];

        } elseif ($assignment->status === 'in_progress') {
            $draftFiles = [];
            if ($assignment->draft_review_files) {
                if (is_string($assignment->draft_review_files)) {
                    $draftFilesData = json_decode($assignment->draft_review_files, true) ?? [];
                } else {
                    $draftFilesData = $assignment->draft_review_files;
                }

                foreach ($draftFilesData as $file) {
                    if (is_array($file)) {
                        $draftFiles[] = [
                            'path' => $file['path'],
                            'name' => $file['original_name'] ?? null,
                        ];
                    } else {
                        $draftFiles[] = [
                            'path' => $file,
                            'name' => $file->original_name
                        ];
                    }
                }
            }

            $reviewData = [
                'draft_files' => $draftFiles,
                'in_progress_at' => $assignment->in_progress_at,
                'deadline' => $assignment->deadline,
            ];

        } else {
            $reviewData = [
                'type' => 'assigned_review',
                'assigned_at' => $assignment->assigned_at,
                'deadline' => $assignment->deadline,
            ];
        }

        return response()->json([
            'status' => true,
            'data' => [
                'id' => $assignment->reviewer->id,
                'name' => $assignment->reviewer->name,
                'description' => $assignment->article->description,
                'current_status' => $assignment->status,
                'status_name' => $assignment->status_name,
                'review_data' => $reviewData,
                'status_dates' => [
                    'created_at' => $assignment->created_at,
                    'assigned_at' => $assignment->assigned_at,
                    'in_progress_at' => $assignment->in_progress_at,
                    'refused_at' => $assignment->refused_at,
                    'completed_at' => $assignment->completed_at,
                    'status_changed_at' => $assignment->status_changed_at,
                    'deadline' => $assignment->deadline,
                    'extension_date' => $assignment->deadline_extended_at,
                ],
            ]
        ]);
    }

    public function deadlineExtension(Request $request, $id): JsonResponse
    {
        $reviewers = [];

        try {
            if ($request->has('reviewers')) {
                $reviewersData = $request->reviewers;

                if (is_string($reviewersData)) {
                    $decodedReviewers = json_decode($reviewersData, true);

                    if (json_last_error() !== JSON_ERROR_NONE) {
                        return response()->json([
                            'status' => false,
                            'message' => 'Invalid JSON format: ' . json_last_error_msg()
                        ], 422);
                    }

                    $reviewers = $decodedReviewers;
                }
                elseif (is_array($reviewersData)) {
                    $reviewers = $reviewersData;
                }
            }

            if (!is_array($reviewers) || empty($reviewers)) {
                return response()->json([
                    'status' => false,
                    'message' => 'Reviewers ma\'lumotlari noto\'g\'ri formatda'
                ], 422);
            }

            $validator = Validator::make(['reviewers' => $reviewers], [
                'reviewers' => 'required|array|min:1',
                'reviewers.*.reviewer_id' => 'required|exists:users,id',
                'reviewers.*.new_deadline' => 'required|date|after:today',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $article = ArticleReviewer::findOrFail($id);

            $updatedAssignments = [];
            $errors = [];
            $extensionDate = now();

            foreach ($reviewers as $reviewerData) {
                try {
                    $assignment = $article->assignments()
                        ->where('reviewer_id', $reviewerData['reviewer_id'])
                        ->firstOrFail();

                    $oldDeadline = $assignment->deadline;

                    $assignment->update([
                        'deadline' => $reviewerData['new_deadline'],
                        'deadline_extended_at' => $extensionDate,
                    ]);

                    $updatedAssignments[] = [
                        'reviewer_id' => $assignment->reviewer_id,
                        'reviewer_name' => $assignment->reviewer->name,
                        'old_deadline' => $oldDeadline,
                        'new_deadline' => $assignment->deadline,
                        'extended_at' => $extensionDate,
                    ];

                } catch (\Exception $e) {
                    $errors[] = [
                        'reviewer_id' => $reviewerData['reviewer_id'],
                        'error' => 'Reviewer topilmadi yoki boshqa xatolik: ' . $e->getMessage()
                    ];
                }
            }

            return response()->json([
                'status' => true,
                'data' => [
                    'article' => [
                        'id' => $article->id,
                        'title' => $article->title,
                    ],
                    'extension_date' => $extensionDate,
                    'updated_count' => count($updatedAssignments),
                    'error_count' => count($errors),
                    'updated_assignments' => $updatedAssignments,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Server xatoligi: ' . $e->getMessage(),
                'debug' => [
                    'input_reviewers' => $request->reviewers,
                    'input_type' => gettype($request->reviewers)
                ]
            ], 500);
        }
    }

    public function addReviewer(Request $request, $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'reviewers' => 'required|array|min:1',
            'reviewers.*.reviewer_id' => [
                'required',
                'exists:users,id',
                function ($attribute, $value, $fail) {
                    $user = User::find($value);
                    if (!$user || !$user->hasRole('reviewer') || $user->status !== 'approved') {
                        $fail('Faqat Chief Editor tomonidan tasdiqlangan reviewerlarni qo\'shish mumkin.');
                    }
                },
            ],
            'reviewers.*.deadline' => 'required|date|after:today',
            'reviewers.*.comment' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $article = ArticleReviewer::findOrFail($id);

        $addedReviewers = [];
        $errors = [];
        $duplicates = [];
        $unapproved = [];

        foreach ($request->reviewers as $reviewerData) {
            $reviewerId = $reviewerData['reviewer_id'];
            $user = User::find($reviewerId);

            if (!$user->hasRole('reviewer') || $user->status !== 'approved') {
                $unapproved[] = [
                    'reviewer_id' => $reviewerId,
                    'reviewer_name' => $user->name,
                    'email' => $user->email,
                    'status' => $user->status ?? 'unknown',
                    'message' => 'Chief Editor tomonidan tasdiqlanmagan'
                ];
                continue;
            }

            $existingAssignment = $article->assignments()
                ->where('reviewer_id', $reviewerId)
                ->first();

            if ($existingAssignment) {
                $duplicates[] = [
                    'reviewer_id' => $reviewerId,
                    'reviewer_name' => $existingAssignment->reviewer->name,
                    'current_status' => $existingAssignment->status,
                    'current_deadline' => $existingAssignment->deadline,
                    'message' => 'Bu reviewer allaqachon biriktirilgan'
                ];
                continue;
            }

            try {
                $assignment = $article->assignments()->create([
                    'reviewer_id' => $reviewerId,
                    'assigned_at' => now(),
                    'deadline' => $reviewerData['deadline'],
                    'status' => 'assigned',
                    'comment' => $reviewerData['comment'] ?? null,
                ]);

                $addedReviewers[] = [
                    'assignment_id' => $assignment->id,
                    'reviewer_id' => $assignment->reviewer_id,
                    'reviewer_name' => $assignment->reviewer->name,
                    'reviewer_email' => $assignment->reviewer->email,
                    'deadline' => $assignment->deadline,
                    'status' => $assignment->status,
                    'assigned_at' => $assignment->assigned_at,
                    'is_approved' => true,
                ];

            } catch (\Exception $e) {
                $errors[] = [
                    'reviewer_id' => $reviewerId,
                    'error' => 'Reviewer qo\'shishda xatolik: ' . $e->getMessage()
                ];
            }
        }

        $message = '';
        if (count($addedReviewers) > 0) {
            $message .= count($addedReviewers) . ' ta reviewer muvaffaqiyatli qo\'shildi. ';
        }
        if (count($duplicates) > 0) {
            $message .= count($duplicates) . ' ta reviewer allaqachon mavjud. ';
        }
        if (count($unapproved) > 0) {
            $message .= count($unapproved) . ' ta reviewer tasdiqlanmagan. ';
        }
        if (count($errors) > 0) {
            $message .= count($errors) . ' ta xatolik yuz berdi.';
        }

        return response()->json([
            'status' => true,
            'message' => $message ?: 'Hech qanday o\'zgarish bo\'lmadi',
            'data' => [
                'article' => [
                    'id' => $article->id,
                    'title' => $article->title,
                    'total_reviewers' => $article->assignments()->count(),
                ],
                'added_reviewers' => $addedReviewers,
                'duplicates' => $duplicates,
                'unapproved' => $unapproved,
                'errors' => $errors,
                'summary' => [
                    'added_count' => count($addedReviewers),
                    'duplicate_count' => count($duplicates),
                    'unapproved_count' => count($unapproved),
                    'error_count' => count($errors),
                ]
            ]
        ]);
    }

    public function getAvailableReviewers($id): JsonResponse
    {
        $assignedReviewerIds = [];

        $articleReviewer = ArticleReviewer::find($id);
        if ($articleReviewer) {
            $assignedReviewerIds = $articleReviewer->assignments()->pluck('reviewer_id')->toArray();
        }

        $articleConsideration = ArticleConsideration::find($id);
        if ($articleConsideration) {
        }

        if (!$articleReviewer && !$articleConsideration) {
            return response()->json([
                'status' => false,
                'message' => 'Maqola topilmadi'
            ], 404);
        }

        $availableReviewers = User::whereHas('roles', function($q) {
                $q->where('name', 'reviewer');
            })
            ->where('active', 1)
            ->whereNotIn('id', $assignedReviewerIds)
            ->select('id', 'name')
            ->get();

        return response()->json([
            'status' => true,
            'data' => $availableReviewers
        ]);
    }

    public function reviews($id): JsonResponse
    {
        $article = ArticleReviewer::findOrFail($id);

        $assignments = $article->assignments()
            ->with(['reviewer'])
            ->where('status', 'completed')
            ->get();

        $reviews = $assignments->map(function ($assignment) {
            $reviewCriteria = ReviewCriteria::active()->get();
            $savedScores = $assignment->criteria_scores ?? [];

            $scores = [];
            foreach ($reviewCriteria as $criterion) {
                $scores[] = [
                    'id' => $criterion->id,
                    'name' => $criterion->name_ru,
                    'score' => $savedScores[$criterion->id] ?? null,
                    'max_score' => $criterion->max_score,
                ];
            }

            return [
                'reviewer' => [
                    'id' => $assignment->reviewer->id,
                    'name' => $assignment->reviewer->name,
                    'email' => $assignment->reviewer->email,
                ],
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
        });

        return response()->json([
            'status' => true,
            'data' => [
                'article' => [
                    'id' => $article->id,
                    'title' => $article->title,
                ],
                'reviews' => $reviews,
                'total_reviews' => $reviews->count(),
            ]
        ]);
    }
}
