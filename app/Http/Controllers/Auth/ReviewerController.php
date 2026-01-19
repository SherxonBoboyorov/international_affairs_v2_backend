<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
class ReviewerController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'institutional_phone' => 'nullable|string|max:20',
            'academic_degree' => 'required|string|max:255',
            'work_place' => 'required|string|max:255',
            'position' => 'required|string|max:255',
            'science_field' => 'nullable|file|mimes:pdf,doc,docx',
            'diploma_issued_by' => 'required|string|max:255',
            'orcid' => 'nullable|string|max:255',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ], 422);
        }
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'active' => false,
        ]);
        if ($request->hasFile('science_field')) {
            $scienceFieldFile = $request->file('science_field');
            $scienceFieldPath = $scienceFieldFile->store('science_fields', 'public');
        }
        UserDocument::create([
            'user_id' => $user->id,
            'institutional_phone' => $request->institutional_phone,
            'academic_degree' => $request->academic_degree,
            'work_place' => $request->work_place,
            'position' => $request->position,
            'science_field' => $scienceFieldPath,
            'diploma_issued_by' => $request->diploma_issued_by,
            'orcid' => $request->orcid,
        ]);
        $user->assignRole('reviewer');
        return response()->json([
            'status' => true,
            'message' => 'Muvaffaqiyatli ro\'yxatdan o\'tdingiz. Chief Editor tomonidan tasdiqlanishingiz kerak.',
            'data' => [
                'user_id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => 'reviewer'
            ]
        ]);
    }

    public function updateProfile(Request $request)
    {
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|string|email|max:255|unique:users,email,' . $user->id,
            'institutional_phone' => 'nullable|string|max:20',
            'academic_degree' => 'sometimes|required|string|max:255',
            'work_place' => 'sometimes|required|string|max:255',
            'position' => 'sometimes|required|string|max:255',
            'science_field' => 'nullable|file|mimes:pdf,doc,docx',
            'diploma_issued_by' => 'sometimes|required|string|max:255',
            'orcid' => 'nullable|string|max:255',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ], 422);
        }
        $userData = $request->only(['name', 'email']);



        $user->update($userData);
        $documentData = $request->only([
            'institutional_phone', 'academic_degree', 'work_place',
            'position', 'science_field', 'diploma_issued_by', 'orcid'
        ]);
        if ($request->hasFile('science_field')) {
            $scienceFieldFile = $request->file('science_field');
            $documentData['science_field_path'] = $scienceFieldFile->store('science_fields', 'public');
        }
        $user->userDocument()->updateOrCreate(
            ['user_id' => $user->id],
            $documentData
        );
        return response()->json([
            'status' => true,
            'message' => 'Profil muvaffaqiyatli yangilandi'
        ]);
    }
}

