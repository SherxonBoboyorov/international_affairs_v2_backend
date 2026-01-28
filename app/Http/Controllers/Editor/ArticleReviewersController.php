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
        $data = $request->all();

        if ($request->hasFile('file')) {
            $filePath = $request->file('file')->store('article_reviewers', 'public');
            $data['file_path'] = $filePath;
        }

        if ($request->hasFile('edited_file')) {
            $editedFilePath = $request->file('edited_file')->store('article_reviewers/edited', 'public');
            $data['edited_file_path'] = $editedFilePath;
        }

        $article = ArticleReviewer::create([
            'title' => $request->title,
            'fio' => $request->fio,
            'file_path' => $filePath,
            'edited_file_path' => $editedFilePath ?? null,
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
            $editedFilePath = $request->file('edited_file')->store('article_reviewers/edited', 'public');
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
                'file_path' => 'https://international-affairs.uz/storage/' . $reviewerArticle->file_path,
                'edited_file_path' => $editedFilePath ? 'https://international-affairs.uz/storage/' . $editedFilePath : null,
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

        $editedFilePath = $request->file('edited_file')->store('article_reviewers/edited', 'public');

        $reviewerArticle->update([
            'edited_file_path' => $editedFilePath,
        ]);

        return response()->json([
            'status' => true,
            'data' => [
                'id' => $reviewerArticle->id,
                'edited_file_path' => $reviewerArticle->edited_file_path,
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
        $article = ArticleReviewer::with(['originalArticle'])->find($id);

        if ($article) {
            return response()->json([
                'status' => true,
                'data' => [
                    'id' => $article->id,
                    'article_title' => $article->title,
                    'authors_name' => $article->fio,
                    'file_path' => $article->file_path,
                    'edited_file_path' => $article->edited_file_path,
                    'deadline' => $article->deadline,
                    'status' => $article->status,
                    'type' => 'internal',
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
