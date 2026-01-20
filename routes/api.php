<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\ReviewerController;
use App\Http\Controllers\Api\ChiefEditorController;
use Illuminate\Support\Facades\Route;

Route::post('register', [ReviewerController::class, 'register']);
Route::post('login', [AuthController::class, 'login']);
Route::post('forgot-password', [ForgotPasswordController::class, 'sendResetCode']);
Route::post('verify-reset-code', [ForgotPasswordController::class, 'verifyCode']);
Route::post('reset-password', [ForgotPasswordController::class, 'reset']);
Route::middleware('auth:sanctum')->group(function () {
    Route::get('profile', [ReviewerController::class, 'profile']);
    Route::put('profile', [ReviewerController::class, 'updateProfile']);

    Route::prefix('chief-editor')->middleware('role:editor')->group(function () {
        Route::get('reviewers/pending', [ChiefEditorController::class, 'pendingReviewers']);
        Route::get('reviewers/approved', [ChiefEditorController::class, 'approvedReviewers']);
        Route::get('reviewers/{id}', [ChiefEditorController::class, 'showReviewer']);
        Route::post('reviewers/{id}/approve', [ChiefEditorController::class, 'approveReviewer']);
        Route::post('reviewers/{id}/reject', [ChiefEditorController::class, 'rejectReviewer']);
    });

    Route::post('logout', [AuthController::class, 'logout']);
});
