<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ForgotPasswordController extends Controller
{
    public function sendResetCode(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'Bunday email topilmadi'
            ], 404);
        }

        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        try {
            Mail::send('emails.password-reset', [
                'code' => $code,
                'user' => $user,
                'expiresAt' => now()->addMinutes(10)->format('H:i')
            ], function ($message) use ($request, $user) {
                $message->to($request->email)
                    ->subject('Parolni tiklash kodi')
                    ->from(config('mail.from.address'), config('mail.from.name'));
            });

            $user->update([
                'email_verification_code' => $code,
                'email_verification_code_expires_at' => now()->addMinutes(10)
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Emailga 6 xonali kod yuborildi'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Email yuborishda xatolik yuz berdi. Iltimos, keyinroq urinib ko\'ring.'
            ], 500);
        }
    }

    public function verifyCode(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'code' => 'required|digits:6'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::where('email', $request->email)
            ->where('email_verification_code', $request->code)
            ->where('email_verification_code_expires_at', '>', now())
            ->first();

        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'Kod noto\'g\'ri yoki muddati o\'tgan'
            ], 400);
        }

        return response()->json([
            'status' => true,
            'message' => 'Kod to\'g\'ri tasdiqlandi',
            'reset_token' => $request->code,
            'user_info' => [
                'name' => $user->name,
                'email' => $user->email
            ]
        ]);
    }

    public function reset(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'reset_token' => 'required|string',
            'password' => 'required|string|min:8'
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ], 422);
        }
        $user = User::where('email', $request->email)
            ->where('email_verification_code', $request->reset_token)
            ->where('email_verification_code_expires_at', '>', now())
            ->first();
        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'Token noto\'g\'ri yoki muddati o\'tgan'
            ], 400);
        }
        $user->update([
            'password' => Hash::make($request->password),
            'email_verification_code' => null,
            'email_verification_code_expires_at' => null
        ]);
        return response()->json([
            'status' => true,
            'message' => 'Parol muvaffaqiyatli yangilandi. Endi tizimga kirishingiz mumkin.'
        ]);
    }
}
