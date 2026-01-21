<?php

namespace App\Http\Controllers\Reviewer;

use App\Http\Controllers\Controller;
use App\Models\Submission;
use App\Models\SubmissionAssignment;
use App\Models\SubmissionReview;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
class ReviewerSubmissionController extends Controller
{
    public function dashboard(): JsonResponse
    {
        $stats = [
            'total_assigned' => SubmissionAssignment::where('reviewer_id', Auth::id())->count(),
            'pending_review' => SubmissionAssignment::where('reviewer_id', Auth::id())
                ->where('status', 'assigned')->count(),
            'in_progress' => SubmissionAssignment::where('reviewer_id', Auth::id())
                ->where('status', 'in_progress')->count(),
            'completed' => SubmissionAssignment::where('reviewer_id', Auth::id())
                ->where('status', 'completed')->count(),
            'overdue' => SubmissionAssignment::where('reviewer_id', Auth::id())
                ->where('status', '!=', 'completed')
                ->where('deadline', '<', now())->count(),
        ];
        $recentAssignments = SubmissionAssignment::where('reviewer_id', Auth::id())
            ->with(['submission.user'])
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get();
        return response()->json([
            'status' => true,
            'data' => [
                'stats' => $stats,
                'recent_assignments' => $recentAssignments
            ]
        ]);
    }
    public function assignedSubmissions(Request $request): JsonResponse
    {
        $query = SubmissionAssignment::where('reviewer_id', Auth::id())
            ->with(['submission.user', 'submission.reviews' => function($q) {
                $q->where('reviewer_id', Auth::id());
            }]);
        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }
        if ($request->has('search')) {
            $search = $request->search;
            $query->whereHas('submission', function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('abstract', 'like', "%{$search}%");
            });
        }
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);
        $perPage = $request->get('per_page', 10);
        $assignments = $query->paginate($perPage);
        return response()->json([
            'status' => true,
            'data' => $assignments
        ]);
    }
    public function showSubmission($submissionId): JsonResponse
    {
        $assignment = SubmissionAssignment::where('submission_id', $submissionId)
            ->where('reviewer_id', Auth::id())
            ->with(['submission.user', 'submission.reviews' => function($q) {
                $q->where('reviewer_id', Auth::id());
            }])
            ->first();
        if (!$assignment) {
            return response()->json([
                'status' => false,
                'message' => 'Bu maqola sizga biriktirilmagan'
            ], 403);
        }
        return response()->json([
            'status' => true,
            'data' => $assignment
        ]);
    }
    public function downloadFile($submissionId): JsonResponse
    {
        $assignment = SubmissionAssignment::where('submission_id', $submissionId)
            ->where('reviewer_id', Auth::id())
            ->first();
        if (!$assignment) {
            return response()->json([
                'status' => false,
                'message' => 'Bu maqola sizga biriktirilmagan'
            ], 403);
        }
        $submission = $assignment->submission;
        if (!$submission->file_path) {
            return response()->json([
                'status' => false,
                'message' => 'Fayl topilmadi'
            ], 404);
        }
        $fileUrl = Storage::disk('public')->url($submission->file_path);
        return response()->json([
            'status' => true,
            'data' => [
                'file_url' => $fileUrl,
                'file_name' => basename($submission->file_path)
            ]
        ]);
    }
    public function submitReview(Request $request, $submissionId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'originality_score' => 'required|integer|min:1|max:5',
            'methodology_score' => 'required|integer|min:1|max:5',
            'argumentation_score' => 'required|integer|min:1|max:5',
            'structure_score' => 'required|integer|min:1|max:5',
            'significance_score' => 'required|integer|min:1|max:5',
            'general_recommendation' => 'required|string|max:1000',
            'comments' => 'nullable|string|max:2000',
            'files' => 'nullable|array',
            'files.*' => 'file|mimes:pdf,doc,docx|max:5120',
            'recommendation' => 'required|in:accept,reject,revision',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ], 422);
        }
        $assignment = SubmissionAssignment::where('submission_id', $submissionId)
            ->where('reviewer_id', Auth::id())
            ->first();
        if (!$assignment) {
            return response()->json([
                'status' => false,
                'message' => 'Bu maqola sizga biriktirilmagan'
            ], 403);
        }
        $files = [];
        if ($request->hasFile('files')) {
            foreach ($request->file('files') as $file) {
                $fileName = time() . '_' . $file->getClientOriginalName();
                $filePath = $file->storeAs('review_files', $fileName, 'public');
                $files[] = $filePath;
            }
        }
        $existingReview = SubmissionReview::where('submission_id', $submissionId)
            ->where('reviewer_id', Auth::id())
            ->first();
        if ($existingReview) {
            $existingReview->update([
                'originality_score' => $request->originality_score,
                'methodology_score' => $request->methodology_score,
                'argumentation_score' => $request->argumentation_score,
                'structure_score' => $request->structure_score,
                'significance_score' => $request->significance_score,
                'general_recommendation' => $request->general_recommendation,
                'comments' => $request->comments,
                'files' => $files,
                'status' => 'submitted',
                'submitted_at' => now(),
            ]);
            $review = $existingReview;
        } else {
            $review = SubmissionReview::create([
                'submission_id' => $submissionId,
                'reviewer_id' => Auth::id(),
                'originality_score' => $request->originality_score,
                'methodology_score' => $request->methodology_score,
                'argumentation_score' => $request->argumentation_score,
                'structure_score' => $request->structure_score,
                'significance_score' => $request->significance_score,
                'general_recommendation' => $request->general_recommendation,
                'comments' => $request->comments,
                'files' => $files,
                'status' => 'submitted',
                'submitted_at' => now(),
            ]);
        }
        $assignment->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);
        return response()->json([
            'status' => true,
            'message' => 'Review muvaffaqiyatli yuborildi',
            'data' => $review
        ]);
    }
    public function updateReview(Request $request, $reviewId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'originality_score' => 'required|integer|min:1|max:5',
            'methodology_score' => 'required|integer|min:1|max:5',
            'argumentation_score' => 'required|integer|min:1|max:5',
            'structure_score' => 'required|integer|min:1|max:5',
            'significance_score' => 'required|integer|min:1|max:5',
            'general_recommendation' => 'required|string|max:1000',
            'comments' => 'nullable|string|max:2000',
            'files' => 'nullable|array',
            'files.*' => 'file|mimes:pdf,doc,docx|max:5120',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ], 422);
        }
        $review = SubmissionReview::where('id', $reviewId)
            ->where('reviewer_id', Auth::id())
            ->first();
        if (!$review) {
            return response()->json([
                'status' => false,
                'message' => 'Review topilmadi'
            ], 404);
        }
        $files = $review->files ?? [];
        if ($request->hasFile('files')) {
            foreach ($request->file('files') as $file) {
                $fileName = time() . '_' . $file->getClientOriginalName();
                $filePath = $file->storeAs('review_files', $fileName, 'public');
                $files[] = $filePath;
            }
        }
        $review->update([
            'originality_score' => $request->originality_score,
            'methodology_score' => $request->methodology_score,
            'argumentation_score' => $request->argumentation_score,
            'structure_score' => $request->structure_score,
            'significance_score' => $request->significance_score,
            'general_recommendation' => $request->general_recommendation,
            'comments' => $request->comments,
            'files' => $files,
        ]);
        return response()->json([
            'status' => true,
            'message' => 'Review muvaffaqiyatli yangilandi',
            'data' => $review
        ]);
    }
    public function startReview($submissionId): JsonResponse
    {
        $assignment = SubmissionAssignment::where('submission_id', $submissionId)
            ->where('reviewer_id', Auth::id())
            ->where('status', 'assigned')
            ->first();
        if (!$assignment) {
            return response()->json([
                'status' => false,
                'message' => 'Assignment topilmadi yoki allaqachon boshlangan'
            ], 404);
        }
        $assignment->update(['status' => 'in_progress']);
        return response()->json([
            'status' => true,
            'message' => 'Review boshlandi',
            'data' => $assignment
        ]);
    }
}
