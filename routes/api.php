<?php

use App\Http\Controllers\Api\AchievementController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ContentController;
use App\Http\Controllers\Api\DeviceTokenController;
use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\SystemController;
use App\Http\Controllers\Api\UserActivityController;
use App\Http\Controllers\Internal\CommunityPostPurgeController;
use Illuminate\Support\Facades\Route;

Route::get('/health', [SystemController::class, 'health']);
Route::get('/config', [SystemController::class, 'config']);
Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:mobile-registration');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:mobile-login');
Route::middleware('throttle:mobile-verification')->group(function () {
    Route::post('/verify-email', [AuthController::class, 'verifyEmail']);
    Route::post('/resend-verification-code', [AuthController::class, 'resendVerificationCode']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/verify-reset-code', [AuthController::class, 'verifyResetCode']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
});

Route::get('/events', [EventController::class, 'index']);
Route::get('/events/{event}', [EventController::class, 'show']);
Route::get('/announcements', [ContentController::class, 'announcements']);
Route::get('/training-modules', [ContentController::class, 'trainingModules']);
Route::get('/training-modules/{module}', [ContentController::class, 'showTrainingModule']);
Route::get('/community-posts', [ContentController::class, 'communityPosts']);
Route::post('/internal/community-posts/purge', CommunityPostPurgeController::class)
    ->middleware(['signed', 'throttle:6,1'])
    ->name('internal.community-posts.purge');
Route::post('/paymongo/webhook', [PaymentController::class, 'payMongoWebhook'])
    ->middleware('throttle:payment-webhook');

Route::middleware(['mobile.auth', 'throttle:mobile-api'])->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::patch('/me', [AuthController::class, 'updateProfile']);
    Route::patch('/me/interests', [AuthController::class, 'updateInterests']);
    Route::post('/me/avatar', [AuthController::class, 'updateAvatar']);
    Route::patch('/me/password', [AuthController::class, 'changePassword']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/my-registrations', [UserActivityController::class, 'registrations']);
    Route::get('/my-results', [UserActivityController::class, 'results']);
    Route::get('/achievements', [AchievementController::class, 'index']);
    Route::get('/leaderboard', [AchievementController::class, 'leaderboard']);
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead']);
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead']);
    Route::post('/device-tokens', [DeviceTokenController::class, 'store']);
    Route::delete('/device-tokens', [DeviceTokenController::class, 'destroy']);
    Route::post('/events/{event}/register/{category}', [EventController::class, 'register']);
    Route::get('/registrations/{registration}/payments', [PaymentController::class, 'history']);
    Route::post('/registrations/{registration}/paymongo-checkout', [PaymentController::class, 'createPayMongoCheckout']);
    Route::post('/registrations/{registration}/payment-proof', [PaymentController::class, 'submitProof']);
    Route::get('/community-posts/feed', [ContentController::class, 'communityFeed']);
    Route::get('/community-posts/archived', [ContentController::class, 'archivedCommunityPosts']);
    Route::get('/community-posts/hidden', [ContentController::class, 'hiddenCommunityPosts']);
    Route::get('/community-posts/reported', [ContentController::class, 'reportedCommunityPosts']);
    Route::get('/community-posts/{post}', [ContentController::class, 'showCommunityPost']);
    Route::post('/community-posts', [ContentController::class, 'storeCommunityPost']);
    Route::patch('/community-posts/{post}', [ContentController::class, 'updateCommunityPost']);
    Route::post('/community-posts/{post}/hide', [ContentController::class, 'hideCommunityPost']);
    Route::delete('/community-posts/{post}/hide', [ContentController::class, 'unhideCommunityPost']);
    Route::post('/community-posts/{post}/report', [ContentController::class, 'reportCommunityPost']);
    Route::post('/community-posts/{post}/comments', [ContentController::class, 'storeCommunityPostComment']);
    Route::post('/community-posts/{post}/like', [ContentController::class, 'toggleCommunityPostLike']);
    Route::delete('/community-posts/{post}', [ContentController::class, 'destroyCommunityPost']);
    Route::post('/community-posts/{post}/restore', [ContentController::class, 'restoreCommunityPost']);
});
