<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ChiefEditorController extends Controller
{
    /**
     * @return \Illuminate\Http\JsonResponse
     */
    public function pendingReviewers(): JsonResponse
    {
        $reviewers = User::role('reviewer')
            ->where('active', false)
            ->with('userDocument')
            ->get();

        return response()->json([
            'status' => true,
            'data' => $reviewers
        ]);
    }

    /**
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function approveReviewer($id): JsonResponse
    {
        $reviewer = User::find($id);

        if (!$reviewer) {
            return response()->json([
                'status' => false,
                'message' => 'Foydalanuvchi topilmadi'
            ], 404);
        }

        if (!$reviewer->hasRole('reviewer')) {
            return response()->json([
                'status' => false,
                'message' => 'Bu foydalanuvchi reviewer emas'
            ], 400);
        }

        $reviewer->update(['active' => true]);

        return response()->json([
            'status' => true,
            'message' => 'Reviewer muvaffaqiyatli tasdiqlandi',
            'data' => $reviewer->load('userDocument')
        ]);
    }

    /**
     * Reviewerni rad etish
     *
     * @param int $id
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function rejectReviewer($id, Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|max:1000'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $reviewer = User::find($id);

        if (!$reviewer) {
            return response()->json([
                'status' => false,
                'message' => 'Foydalanuvchi topilmadi'
            ], 404);
        }

        if (!$reviewer->hasRole('reviewer')) {
            return response()->json([
                'status' => false,
                'message' => 'Bu foydalanuvchi reviewer emas'
            ], 400);
        }

        $reviewer->userDocument()->updateOrCreate(
            ['user_id' => $reviewer->id],
            ['rejection_reason' => $request->reason]
        );

        $reviewer->delete();

        return response()->json([
            'status' => true,
            'message' => 'Reviewer muvaffaqiyatli rad etildi'
        ]);
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     */
    public function approvedReviewers(): JsonResponse
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

    /**
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function showReviewer($id): JsonResponse
    {
        $reviewer = User::role('reviewer')
            ->with('userDocument')
            ->find($id);

        if (!$reviewer) {
            return response()->json([
                'status' => false,
                'message' => 'Reviewer topilmadi'
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data' => $reviewer
        ]);
    }
}
