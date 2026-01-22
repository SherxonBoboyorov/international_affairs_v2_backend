<?php

namespace App\Http\Controllers\Editor;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ChiefEditorController extends Controller
{
    public function pendingReviewers(Request $request): JsonResponse
    {
        $query = User::role('reviewer')
            ->where('active', false)
            ->with('userDocument.scienceField');
        if ($request->has('science_field_id') && $request->science_field_id) {
            $query->whereHas('userDocument', function ($q) use ($request) {
                $q->where('science_field_id', $request->science_field_id);
            });
        }
        if ($request->has('created_at') && $request->created_at) {
            $query->whereDate('created_at', $request->created_at);
        }
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy('created_at', $sortOrder);
        $perPage = $request->get('per_page', 10);
        $reviewers = $query->paginate($perPage);
        $reviewers->getCollection()->transform(function ($reviewer) {
            return [
                'id' => $reviewer->id,
                'name' => $reviewer->name,
                'email' => $reviewer->email,
                'created_at' => $reviewer->created_at,
                'updated_at' => $reviewer->updated_at,
                'user_document_id' => $reviewer->userDocument->id,
                'institutional_phone' => $reviewer->userDocument->institutional_phone,
                'academic_degree' => $reviewer->userDocument->academic_degree,
                'work_place' => $reviewer->userDocument->work_place,
                'position' => $reviewer->userDocument->position,
                'science_field_id' => $reviewer->userDocument->science_field_id,
                'diploma_issued_by' => $reviewer->userDocument->diploma_issued_by,
                'orcid' => $reviewer->userDocument->orcid,
                'scientific_activity' => $reviewer->userDocument->scienceField,
            ];
        });
        return response()->json([
            'status' => true,
            'data' => $reviewers
        ]);
    }

    public function approvedReviewers(Request $request): JsonResponse
    {
        $query = User::role('reviewer')
            ->where('active', true)
            ->with('userDocument.scienceField');
        if ($request->has('science_field_id') && $request->science_field_id) {
            $query->whereHas('userDocument', function ($q) use ($request) {
                $q->where('science_field_id', $request->science_field_id);
            });
        }
        if ($request->has('created_at') && $request->created_at) {
            $query->whereDate('created_at', $request->created_at);
        }
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy('created_at', $sortOrder);
        $perPage = $request->get('per_page', 12);
        $reviewers = $query->paginate($perPage);
        $reviewers->getCollection()->transform(function ($reviewer) {
            return [
                'id' => $reviewer->id,
                'name' => $reviewer->name,
                'email' => $reviewer->email,
                'created_at' => $reviewer->created_at,
                'updated_at' => $reviewer->updated_at,
                'user_document_id' => $reviewer->userDocument->id,
                'institutional_phone' => $reviewer->userDocument->institutional_phone,
                'academic_degree' => $reviewer->userDocument->academic_degree,
                'work_place' => $reviewer->userDocument->work_place,
                'position' => $reviewer->userDocument->position,
                'science_field_id' => $reviewer->userDocument->science_field_id,
                'diploma_issued_by' => $reviewer->userDocument->diploma_issued_by,
                'orcid' => $reviewer->userDocument->orcid,
                'scientific_activity' => $reviewer->userDocument->scienceField,
            ];
        });
        return response()->json([
            'status' => true,
            'data' => $reviewers
        ]);
    }

    public function showReviewer($id): JsonResponse
    {
        $reviewer = User::role('reviewer')
            ->with('userDocument.scienceField')
            ->find($id);
        if (!$reviewer) {
            return response()->json([
                'status' => false,
                'message' => 'Reviewer topilmadi'
            ], 404);
        }
        $data = [
            'id' => $reviewer->id,
            'name' => $reviewer->name,
            'email' => $reviewer->email,
            'created_at' => $reviewer->created_at,
            'updated_at' => $reviewer->updated_at,
            'user_document_id' => $reviewer->userDocument->id,
            'institutional_phone' => $reviewer->userDocument->institutional_phone,
            'academic_degree' => $reviewer->userDocument->academic_degree,
            'work_place' => $reviewer->userDocument->work_place,
            'position' => $reviewer->userDocument->position,
            'science_field_id' => $reviewer->userDocument->science_field_id,
            'diploma_file' => $reviewer->userDocument->diploma_file,
            'diploma_issued_by' => $reviewer->userDocument->diploma_issued_by,
            'orcid' => $reviewer->userDocument->orcid,
            'scientific_activity' => $reviewer->userDocument->scienceField,
        ];
        return response()->json([
            'status' => true,
            'data' => $data
        ]);
    }

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
        $data = [
            'id' => $reviewer->id,
            'name' => $reviewer->name,
            'email' => $reviewer->email,
            'created_at' => $reviewer->created_at,
            'updated_at' => $reviewer->updated_at,
            'user_document_id' => $reviewer->userDocument->id,
            'institutional_phone' => $reviewer->userDocument->institutional_phone,
            'academic_degree' => $reviewer->userDocument->academic_degree,
            'work_place' => $reviewer->userDocument->work_place,
            'position' => $reviewer->userDocument->position,
            'science_field_id' => $reviewer->userDocument->science_field_id,
            'diploma_issued_by' => $reviewer->userDocument->diploma_issued_by,
            'orcid' => $reviewer->userDocument->orcid,
            'scientific_activity' => $reviewer->userDocument->scienceField,
        ];
        return response()->json([
            'status' => true,
            'message' => 'Reviewer muvaffaqiyatli tasdiqlandi',
            'data' => $data
        ]);
    }

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

    public function archivedReviewers(Request $request): JsonResponse
    {
        $query = User::onlyTrashed()
            ->role('reviewer')
            ->with('userDocument.scienceField');
        if ($request->has('science_field_id') && $request->science_field_id) {
            $query->whereHas('userDocument', function ($q) use ($request) {
                $q->where('science_field_id', $request->science_field_id);
            });
        }
        if ($request->has('deleted_at') && $request->deleted_at) {
            $query->whereDate('deleted_at', $request->deleted_at);
        }
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy('deleted_at', $sortOrder);
        $perPage = $request->get('per_page', 10);
        $reviewers = $query->paginate($perPage);
        $reviewers->getCollection()->transform(function ($reviewer) {
            return [
                'id' => $reviewer->id,
                'name' => $reviewer->name,
                'email' => $reviewer->email,
                'deleted_at' => $reviewer->deleted_at,
                'user_document_id' => $reviewer->userDocument->id,
                'institutional_phone' => $reviewer->userDocument->institutional_phone,
                'academic_degree' => $reviewer->userDocument->academic_degree,
                'work_place' => $reviewer->userDocument->work_place,
                'position' => $reviewer->userDocument->position,
                'science_field_id' => $reviewer->userDocument->science_field_id,
                'diploma_file' => $reviewer->userDocument->diploma_file,
                'diploma_issued_by' => $reviewer->userDocument->diploma_issued_by,
                'orcid' => $reviewer->userDocument->orcid,
                'scientific_activity' => $reviewer->userDocument->scienceField,
                'rejection_reason' => $reviewer->userDocument->rejection_reason,
            ];
        });
        return response()->json([
            'status' => true,
            'data' => $reviewers
        ]);
    }

    public function showArchivedReviewer($id): JsonResponse
    {
        $reviewer = User::onlyTrashed()
            ->role('reviewer')
            ->with('userDocument.scienceField')
            ->find($id);
        if (!$reviewer) {
            return response()->json([
                'status' => false,
                'message' => 'Архивda reviewer topilmadi'
            ], 404);
        }
        $data = [
            'id' => $reviewer->id,
            'name' => $reviewer->name,
            'email' => $reviewer->email,
            'created_at' => $reviewer->created_at,
            'updated_at' => $reviewer->updated_at,
            'deleted_at' => $reviewer->deleted_at,
            'user_document_id' => $reviewer->userDocument->id,
            'institutional_phone' => $reviewer->userDocument->institutional_phone,
            'academic_degree' => $reviewer->userDocument->academic_degree,
            'work_place' => $reviewer->userDocument->work_place,
            'position' => $reviewer->userDocument->position,
            'science_field_id' => $reviewer->userDocument->science_field_id,
            'diploma_file' => $reviewer->userDocument->diploma_file,
            'diploma_issued_by' => $reviewer->userDocument->diploma_issued_by,
            'orcid' => $reviewer->userDocument->orcid,
            'scientific_activity' => $reviewer->userDocument->scienceField,
            'rejection_reason' => $reviewer->userDocument->rejection_reason,
        ];
        return response()->json([
            'status' => true,
            'data' => $data
        ]);
    }
}
