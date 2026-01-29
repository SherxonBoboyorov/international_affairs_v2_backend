<?php

namespace App\Http\Controllers\Editor;

use App\Http\Controllers\Controller;
use App\Models\ArticleConsideration;
use App\Models\ArticleReviewer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
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
            'created_by' => auth()->id(),
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
            'edited_file' => 'required|file|mimes:pdf,doc,docx|max:10240',
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

        $reviewerArticle = ArticleReviewer::create([
            'title' => $request->title,
            'fio' => $request->fio,
            'file_path' => $article->article_file,
            'edited_file_path' => $editedFilePath,
            'deadline' => $request->deadline,
            'description' => $request->description,
            'status' => 'not_assigned',
            'created_by' => auth()->id(),
        ]);

        $article->update(['status' => 'converted']);

        return response()->json([
            'status' => true,
            'message' => 'Maqola muvaffaqiyatli tahrirlandi va saqlandi',
            'data' => [
                'id' => $reviewerArticle->id,
                'title' => $reviewerArticle->title,
                'fio' => $reviewerArticle->fio,
                'file_path' => $reviewerArticle->file_path
                    ? asset('storage/' . $reviewerArticle->file_path)
                    : null,
                'edited_file_path' => $editedFilePath
                    ? asset('storage/' . $editedFilePath)
                    : null,
                'deadline' => $reviewerArticle->deadline,
                'status' => $reviewerArticle->status,
                'type' => 'internal',
                'created_at' => $reviewerArticle->created_at
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
                'created_by' => auth()->id(),
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
                    'reviewer' => [
                        'id' => $assignment->reviewer->id,
                        'name' => $assignment->reviewer->name,
                        'email' => $assignment->reviewer->email
                    ],
                    'status' => $assignment->status,
                    'status_name' => $assignment->status_name,
                    'assigned_at' => $assignment->assigned_at,
                    'deadline' => $assignment->deadline,
                    'completed_at' => $assignment->completed_at,
                    'is_overdue' => $assignment->is_overdue,
                    'comment' => $assignment->comment,
                    'review_completed' => $assignment->status === 'completed',
                    'review_data' => $assignment->status === 'completed' ? [
                        'originality_score' => $assignment->originality_score,
                        'methodology_score' => $assignment->methodology_score,
                        'argumentation_score' => $assignment->argumentation_score,
                        'structure_score' => $assignment->structure_score,
                        'significance_score' => $assignment->significance_score,
                        'general_recommendation' => $assignment->general_recommendation,
                        'review_comments' => $assignment->review_comments,
                        'review_files' => $assignment->review_files
                    ] : null
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
                'pending_reviews' => $assignments->where('review_completed', false)->count(),
            ];

            return response()->json([
                'status' => true,
                'data' => [
                    'id' => $article->id,
                    'title' => $article->title,
                    'fio' => $article->fio,
                    'description' => $article->description,
                    'file_path' => $article->file_path ?
                        'https://international-affairs.uz/storage/' . $article->file_path : null,
                    'edited_file_path' => $article->edited_file_path ?
                        'https://international-affairs.uz/storage/' . $article->edited_file_path : null,
                    'active_file_path' => $activeFilePath ?
                        'https://international-affairs.uz/storage/' . $activeFilePath : null,
                    'deadline' => $article->deadline,
                    'status' => $article->status,
                    'type' => 'internal',
                    'created_at' => $article->created_at,
                    'updated_at' => $article->updated_at,
                    'original_article' => $article->originalArticle,

                    'assignments' => $assignments,
                    'assignments_by_status' => $assignmentsByStatus,
                    'assignments_summary' => $summary,

                    'has_assignments' => $assignments->count() > 0,
                    'all_reviews_completed' => $assignments->count() > 0 && $assignments->where('review_completed', false)->count() === 0,
                    'has_overdue_reviews' => $assignments->where('is_overdue')->count() > 0,
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
                    'type' => 'external',

                ]
            ]);
        }

        return response()->json([
            'status' => false,
            'message' => 'Maqola topilmadi'
        ], 404);
    }
}
