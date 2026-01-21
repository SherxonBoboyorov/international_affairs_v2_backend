<?php

namespace App\Http\Controllers\Editor;

use App\Http\Controllers\Controller;
use App\Models\Submission;
use App\Models\SubmissionAssignment;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class ChiefEditorSubmissionController extends Controller
{
    public function dashboard(): JsonResponse
    {
        $stats = [
            'total_submissions' => Submission::count(),
            'pending_submissions' => Submission::where('status', 'pending')->count(),
            'under_review' => Submission::where('status', 'under_review')->count(),
            'accepted' => Submission::where('status', 'accepted')->count(),
            'rejected' => Submission::where('status', 'rejected')->count(),
            'revisions_required' => Submission::where('status', 'revisions_required')->count(),
            'active_reviewers' => User::role('reviewer')->where('active', true)->count(),
            'pending_reviewers' => User::role('reviewer')->where('active', false)->count(),
        ];
        $recentSubmissions = Submission::with('user')
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get();
        return response()->json([
            'status' => true,
            'data' => [
                'stats' => $stats,
                'recent_submissions' => $recentSubmissions
            ]
        ]);
    }
    public function index(Request $request): JsonResponse
    {
        $query = Submission::with(['user', 'reviews.reviewer', 'assignments.reviewer']);
        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('abstract', 'like', "%{$search}%")
                  ->orWhereHas('user', function ($userQuery) use ($search) {
                      $userQuery->where('name', 'like', "%{$search}%")
                               ->orWhere('email', 'like', "%{$search}%");
                  });
            });
        }
        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);
        $perPage = $request->get('per_page', 10);
        $submissions = $query->paginate($perPage);
        return response()->json([
            'status' => true,
            'data' => $submissions
        ]);
    }
    public function show($id): JsonResponse
    {
        $submission = Submission::with([
            'user',
            'reviews.reviewer',
            'assignments.reviewer',
            'assignments.assignedBy'
        ])->findOrFail($id);
        return response()->json([
            'status' => true,
            'data' => $submission
        ]);
    }
    public function getReviewers(): JsonResponse
    {
        $reviewers = User::role('reviewer')
            ->where('active', true)
            ->with('userDocument')
            ->get();
        return response()->json([
            'status' => true,
            'data' => $reviewers
        ]);
    }

    public function assignReviewer(Request $request, $submissionId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'reviewer_id' => 'required|exists:users,id',
            'deadline' => 'nullable|date|after:today',
            'message' => 'nullable|string|max:500',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ], 422);
        }
        $submission = Submission::findOrFail($submissionId);
        $reviewer = User::findOrFail($request->reviewer_id);
        if (!$reviewer->hasRole('reviewer') || !$reviewer->active) {
            return response()->json([
                'status' => false,
                'message' => 'Reviewer faol emas yoki mavjud emas'
            ], 422);
        }
        $existingAssignment = SubmissionAssignment::where('submission_id', $submissionId)
            ->where('reviewer_id', $request->reviewer_id)
            ->first();
        if ($existingAssignment) {
            return response()->json([
                'status' => false,
                'message' => 'Bu maqola allaqachon shu reviewerga biriktirilgan'
            ], 422);
        }
        $assignment = SubmissionAssignment::create([
            'submission_id' => $submissionId,
            'reviewer_id' => $request->reviewer_id,
            'assigned_by' => Auth::id(),
            'assigned_at' => now(),
            'deadline' => $request->deadline,
            'status' => 'assigned',
        ]);
        $submission->update(['status' => 'under_review']);
        return response()->json([
            'status' => true,
            'message' => 'Maqola reviewerga muvaffaqiyatli biriktirildi',
            'data' => $assignment->load(['submission', 'reviewer', 'assignedBy'])
        ]);
    }
    public function removeAssignment($submissionId, $assignmentId): JsonResponse
    {
        $assignment = SubmissionAssignment::where('id', $assignmentId)
            ->where('submission_id', $submissionId)
            ->firstOrFail();
        $assignment->delete();
        return response()->json([
            'status' => true,
            'message' => 'Reviewer biriktirilishi bekor qilindi'
        ]);
    }
    public function updateStatus(Request $request, $submissionId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:accepted,rejected,revisions_required',
            'comment' => 'nullable|string|max:1000',
            'send_email' => 'boolean',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ], 422);
        }
        $submission = Submission::findOrFail($submissionId);
        $submission->update([
            'status' => $request->status,
        ]);
        return response()->json([
            'status' => true,
            'message' => 'Maqola statusi muvaffaqiyatli o\'zgartirildi',
            'data' => $submission
        ]);
    }
    public function destroy($id): JsonResponse
    {
        $submission = Submission::findOrFail($id);

        if ($submission->file_path) {
            Storage::disk('public')->delete($submission->file_path);
        }

        $submission->delete();
        return response()->json([
            'status' => true,
            'message' => 'Maqola muvaffaqiyatli o\'chirildi'
        ]);
    }
    public function downloadFile($id): JsonResponse
    {
        $submission = Submission::findOrFail($id);
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
}
