<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::withTrashed()->where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'status' => false,
                'message' => 'Email yoki parol noto\'g\'ri'
            ], 401);
        }

        if ($user->trashed()) {
            return response()->json([
                'status' => false,
                'message' => 'Sizning profilingiz o\'chirilgan. Iltimos, qayta ro\'yxatdan o\'ting.'
            ], 403);
        }

        if (!$user->active) {
            return response()->json([
                'status' => false,
                'message' => 'Sizning hisobingiz hali tasdiqlanmagan'
            ], 403);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'status' => true,
            'data' => [
                'user' => $user->load('userDocument'),
                'token' => $token,
                'roles' => $user->getRoleNames(),
            ]
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json([
            'status' => true,
            'message' => 'Muvaffaqiyatli chiqish qilindi'
        ]);
    }
}
