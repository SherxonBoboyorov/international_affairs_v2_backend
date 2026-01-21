<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\ReviewerController;
use App\Http\Controllers\Editor\ChiefEditorController;
use App\Http\Controllers\Editor\ChiefEditorSubmissionController;
use App\Http\Controllers\RequirementsController;
use App\Http\Controllers\Reviewer\ReviewerSubmissionController;
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
        Route::get('dashboard', [ChiefEditorSubmissionController::class, 'dashboard']);
        Route::get('reviewers/pending', [ChiefEditorController::class, 'pendingReviewers']);
        Route::get('reviewers/approved', [ChiefEditorController::class, 'approvedReviewers']);
        Route::get('reviewers/{id}', [ChiefEditorController::class, 'showReviewer']);
        Route::get('reviewers/available', [ChiefEditorSubmissionController::class, 'getReviewers']);
        Route::post('reviewers/{id}/approve', [ChiefEditorController::class, 'approveReviewer']);
        Route::post('reviewers/{id}/reject', [ChiefEditorController::class, 'rejectReviewer']);
        Route::prefix('submissions')->group(function () {
            Route::get('/', [ChiefEditorSubmissionController::class, 'index']);
            Route::get('{id}', [ChiefEditorSubmissionController::class, 'show']);
            Route::post('{id}/assign-reviewer', [ChiefEditorSubmissionController::class, 'assignReviewer']);
            Route::delete('{id}/assignments/{assignmentId}', [ChiefEditorSubmissionController::class, 'removeAssignment']);
            Route::put('{id}/status', [ChiefEditorSubmissionController::class, 'updateStatus']);
            Route::delete('{id}', [ChiefEditorSubmissionController::class, 'destroy']);
            Route::get('{id}/download', [ChiefEditorSubmissionController::class, 'downloadFile']);
        });
    });

    Route::prefix('reviewer')->middleware('role:reviewer')->group(function () {
        Route::get('dashboard', [ReviewerSubmissionController::class, 'dashboard']);
        Route::prefix('submissions')->group(function () {
            Route::get('assigned', [ReviewerSubmissionController::class, 'assignedSubmissions']);
            Route::get('{id}', [ReviewerSubmissionController::class, 'showSubmission']);
            Route::get('{id}/download', [ReviewerSubmissionController::class, 'downloadFile']);
            Route::post('{id}/start', [ReviewerSubmissionController::class, 'startReview']);
            Route::post('{id}/review', [ReviewerSubmissionController::class, 'submitReview']);
            Route::put('review/{reviewId}', [ReviewerSubmissionController::class, 'updateReview']);
        });
    });

    Route::post('logout', [AuthController::class, 'logout']);
});


Route::group([
    'prefix' => 'requirements'
], function () {
    Route::get('scientific-activity', [RequirementsController::class, 'scientificActivity']);
});
