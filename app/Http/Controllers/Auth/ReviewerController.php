<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class ReviewerController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255',
            'password' => 'required|string|min:8',
            'institutional_phone' => 'nullable|string|max:20',
            'academic_degree' => 'required|string|max:255',
            'work_place' => 'required|string|max:255',
            'position' => 'required|string|max:255',
            'science_field_id' => 'required|exists:scientific_activities,id',
            'diploma_file' => 'required|file|mimes:pdf,doc,docx|max:5120',
            'diploma_issued_by' => 'required|string|max:255',
            'orcid' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $existingUser = User::withTrashed()
            ->where('email', $request->email)
            ->first();

        if ($existingUser) {
            if ($existingUser->trashed()) {
                $existingUser->restore();
                $existingUser->update([
                    'name' => $request->name,
                    'password' => Hash::make($request->password),
                    'active' => false,
                ]);

                $diplomaFilePath = $existingUser->userDocument->diploma_file;
                if ($request->hasFile('diploma_file')) {
                    if ($diplomaFilePath) {
                        Storage::disk('public')->delete($diplomaFilePath);
                    }
                    $file = $request->file('diploma_file');
                    $fileName = time() . '_' . $file->getClientOriginalName();
                    $diplomaFilePath = $file->storeAs('diplomas', $fileName, 'public');
                }

                $existingUser->userDocument->update([
                    'institutional_phone' => $request->institutional_phone,
                    'academic_degree' => $request->academic_degree,
                    'work_place' => $request->work_place,
                    'position' => $request->position,
                    'science_field_id' => $request->science_field_id,
                    'diploma_file' => $diplomaFilePath,
                    'diploma_issued_by' => $request->diploma_issued_by,
                    'orcid' => $request->orcid,
                    'rejection_reason' => null,
                ]);

                return response()->json([
                    'status' => true,
                    'message' => 'Sizning ma\'lumotlaringiz qayta tiklandi. Chief Editor tasdiqlashini kuting.',
                    'data' => $existingUser->load('userDocument.scienceField')
                ]);
            } else {
                return response()->json([
                    'status' => false,
                    'message' => 'Bu email allaqachon ro\'yxatdan o\'tgan'
                ], 400);
            }
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'active' => false,
        ]);

        $diplomaFilePath = null;
        if ($request->hasFile('diploma_file')) {
            $file = $request->file('diploma_file');
            $fileName = time() . '_' . $file->getClientOriginalName();
            $diplomaFilePath = $file->storeAs('diplomas', $fileName, 'public');
        }

        UserDocument::create([
            'user_id' => $user->id,
            'institutional_phone' => $request->institutional_phone,
            'academic_degree' => $request->academic_degree,
            'work_place' => $request->work_place,
            'position' => $request->position,
            'science_field_id' => $request->science_field_id,
            'diploma_file' => $diplomaFilePath,
            'diploma_issued_by' => $request->diploma_issued_by,
            'orcid' => $request->orcid,
        ]);

        $user->assignRole('reviewer');

        return response()->json([
            'status' => true,
            'message' => 'Reviewer muvaffaqiyatli ro\'yxatdan o\'tdi. Chief Editor tasdiqlashini kuting.',
            'data' => $user->load('userDocument.scienceField')
        ]);
    }

    public function profile(): JsonResponse
    {
        $user = Auth::user()->load([
            'userDocument:id,user_id,institutional_phone,academic_degree,work_place,position,science_field_id,diploma_file,diploma_issued_by,orcid,rejection_reason,created_at,updated_at',
            'userDocument.scienceField:id,title_uz,title_ru,title_en,created_at,updated_at',
        ]);

        return response()->json([
            'status' => true,
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,

                'user_document' => $user->userDocument ? [
                    'id' => $user->userDocument->id,
                    'user_id' => $user->userDocument->user_id,
                    'institutional_phone' => $user->userDocument->institutional_phone,
                    'academic_degree' => $user->userDocument->academic_degree,
                    'work_place' => $user->userDocument->work_place,
                    'position' => $user->userDocument->position,
                    'science_field_id' => $user->userDocument->science_field_id,
                    'diploma_file' => $user->userDocument->diploma_file,
                    'diploma_issued_by' => $user->userDocument->diploma_issued_by,
                    'orcid' => $user->userDocument->orcid,
                    'rejection_reason' => $user->userDocument->rejection_reason,
                    'created_at' => $user->userDocument->created_at,
                    'updated_at' => $user->userDocument->updated_at,

                    'science_field' => $user->userDocument->scienceField ? [
                        'id' => $user->userDocument->scienceField->id,
                        'title_uz' => $user->userDocument->scienceField->title_uz,
                        'title_ru' => $user->userDocument->scienceField->title_ru,
                        'title_en' => $user->userDocument->scienceField->title_en,
                        'created_at' => $user->userDocument->scienceField->created_at,
                        'updated_at' => $user->userDocument->scienceField->updated_at,
                    ] : null,
                ] : null,
            ]
        ]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255',
            'institutional_phone' => 'nullable|string|max:20',
            'academic_degree' => 'required|string|max:255',
            'work_place' => 'required|string|max:255',
            'position' => 'required|string|max:255',
            'science_field_id' => 'required|exists:scientific_activities,id',
            'diploma_file' => 'nullable|file|mimes:pdf,doc,docx|max:5120',
            'diploma_issued_by' => 'required|string|max:255',
            'orcid' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $user->update([
            'name' => $request->name,
            'email' => $request->email
        ]);

        $diplomaFilePath = $user->userDocument->diploma_file;
        if ($request->hasFile('diploma_file')) {
            if ($diplomaFilePath) {
                Storage::disk('public')->delete($diplomaFilePath);
            }

            $file = $request->file('diploma_file');
            $fileName = time() . '_' . $file->getClientOriginalName();
            $diplomaFilePath = $file->storeAs('diplomas', $fileName, 'public');
        }

        $user->userDocument->update([
            'institutional_phone' => $request->institutional_phone,
            'academic_degree' => $request->academic_degree,
            'work_place' => $request->work_place,
            'position' => $request->position,
            'science_field_id' => $request->science_field_id,
            'diploma_file' => $diplomaFilePath,
            'diploma_issued_by' => $request->diploma_issued_by,
            'orcid' => $request->orcid,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Profil muvaffaqiyatli yangilandi',
            'data' => $user->load('userDocument.scienceField')
        ]);
    }


    public function changePassword(Request $request): JsonResponse
    {
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:6'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'status' => false,
                'message' => 'Eski parol noto‘g‘ri.'
            ], 400);
        }

        if (Hash::check($request->password, $user->password)) {
            return response()->json([
                'status' => false,
                'message' => 'Yangi parol eski parol bilan bir xil bo‘lishi mumkin emas.'
            ], 400);
        }

        $user->update([
            'password' => Hash::make($request->password),
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Parol muvaffaqiyatli yangilandi.'
        ]);
    }
}
