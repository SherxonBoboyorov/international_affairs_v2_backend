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
                    'status' => 'not_assigned',
                    'created_at' => $article->created_at,
                    'type' => 'external',
                    'source' => 'article_considerations',
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
        $article = ArticleReviewer::create([
            'title' => $request->title,
            'fio' => $request->fio,
            'file_path' => $filePath,
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

    public function sendToReviewers(Request $request, $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'reviewers' => 'required|array',
            'reviewers.*' => 'exists:users,id',
            'reviewer_deadlines' => 'nullable|array',
            'reviewer_deadlines.*' => 'date|after:today',
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

        if (!$article) {
            return response()->json([
                'status' => false,
                'message' => 'Maqola topilmadi'
            ], 404);
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

            // REVIEWERLARNI BIRIKTIRISH
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

        } else {
            // ArticleReviewer ni statusini yangilash va reviewerlarni biriktirish
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
        }

        return response()->json([
            'status' => true,
            'message' => 'Maqola reviewerlarga yuborildi',
            'data' => $article->load(['reviewers'])
        ]);
    }


    public function overdueArticles(Request $request): JsonResponse
    {
        $articles = ArticleReviewer::where('deadline', '<', now())
            ->where('status', '!=', 'completed')
            ->with(['creator'])
            ->orderBy('deadline', 'asc')
            ->get();
        return response()->json([
            'status' => true,
            'data' => $articles
        ]);
    }

    public function show(Request $request, $id): JsonResponse
    {
        $article = ArticleReviewer::with(['originalArticle', 'creator'])->find($id);

        if (!$article) {
            $article = ArticleConsideration::find($id);
        }
        if (!$article) {
            return response()->json([
                'status' => false,
                'message' => 'Maqola topilmadi'
            ], 404);
        }
        return response()->json([
            'status' => true,
            'data' => $article
        ]);
    }
}
