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
            'email' => 'required|string|email|max:255|unique:users',
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
        $user = Auth::user()->load('userDocument.scienceField');

        return response()->json([
            'status' => true,
            'data' => $user
        ]);
    }
    public function updateProfile(Request $request): JsonResponse
    {
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'institutional_phone' => 'nullable|string|max:20',
            'academic_degree' => 'required|string|max:255',
            'work_place' => 'required|string|max:255',
            'position' => 'required|string|max:255',
            'science_field_id' => 'required|exists:science_fields,id',
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
}

