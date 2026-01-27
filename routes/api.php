<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\ReviewerController;
use App\Http\Controllers\Editor\ArticleReviewersController;
use App\Http\Controllers\Editor\ChiefEditorController;
use App\Http\Controllers\RequirementsController;
use App\Http\Controllers\Reviewer\ArticleController;
use Illuminate\Support\Facades\Route;

Route::post('register', [ReviewerController::class, 'register']);
Route::post('login', [AuthController::class, 'login']);
Route::post('forgot-password', [ForgotPasswordController::class, 'sendResetCode']);
Route::post('verify-reset-code', [ForgotPasswordController::class, 'verifyCode']);
Route::post('reset-password', [ForgotPasswordController::class, 'reset']);
Route::middleware('auth:sanctum')->group(function () {
    Route::get('profile/get', [ReviewerController::class, 'profile']);
    Route::put('profile/update', [ReviewerController::class, 'updateProfile']);
    Route::put('profile/change-password', [ReviewerController::class, 'changePassword']);

    Route::prefix('chief-editor')->middleware('role:editor')->group(function () {
        Route::prefix('reviewer-articles')->group(function () {
            Route::get('/', [ArticleReviewersController::class, 'index']);
            Route::post('/', [ArticleReviewersController::class, 'store']);
            Route::post('{id}/convert', [ArticleReviewersController::class, 'convertToReviewer']);
            Route::get('{id}', [ArticleReviewersController::class, 'show']);
            Route::post('{id}/send-to-reviewers', [ArticleReviewersController::class, 'sendToReviewers']);
        });

        Route::get('reviewers/pending', [ChiefEditorController::class, 'pendingReviewers']);
        Route::get('reviewers/approved', [ChiefEditorController::class, 'approvedReviewers']);
        Route::get('reviewers/{id}', [ChiefEditorController::class, 'showReviewer']);
        Route::post('reviewers/{id}/approve', [ChiefEditorController::class, 'approveReviewer']);
        Route::post('reviewers/{id}/reject', [ChiefEditorController::class, 'rejectReviewer']);
        Route::get('archived-reviewers', [ChiefEditorController::class, 'archivedReviewers']);
        Route::get('archived-reviewers/{id}', [ChiefEditorController::class, 'showArchivedReviewer']);
    });
    Route::prefix('reviewer')->middleware('role:reviewer')->group(function () {
        Route::prefix('articles')->group(function () {
            Route::get('/', [ArticleController::class, 'index']);
            Route::get('{id}', [ArticleController::class, 'show']);
            Route::put('{id}/status', [ArticleController::class, 'updateStatus']);
            Route::post('{id}/submit-review', [ArticleController::class, 'submitReview']);
        });
    });
    Route::post('logout', [AuthController::class, 'logout']);
});

Route::group([
    'prefix' => 'requirements'
], function () {
    Route::get('scientific-activity', [RequirementsController::class, 'scientificActivity']);
});
