<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AchievementController;
use App\Http\Controllers\Api\ContentController;
use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\SystemController;
use App\Http\Controllers\Api\UserActivityController;
use Illuminate\Support\Facades\Route;

Route::get('/health', [SystemController::class, 'health']);
Route::get('/config', [SystemController::class, 'config']);
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);

Route::get('/events', [EventController::class, 'index']);
Route::get('/events/{event}', [EventController::class, 'show']);
Route::get('/announcements', [ContentController::class, 'announcements']);
Route::get('/training-modules', [ContentController::class, 'trainingModules']);
Route::get('/training-modules/{module}', [ContentController::class, 'showTrainingModule']);
Route::get('/community-posts', [ContentController::class, 'communityPosts']);

Route::middleware('mobile.auth')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::patch('/me', [AuthController::class, 'updateProfile']);
    Route::patch('/me/interests', [AuthController::class, 'updateInterests']);
    Route::post('/me/avatar', [AuthController::class, 'updateAvatar']);
    Route::patch('/me/password', [AuthController::class, 'changePassword']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/my-registrations', [UserActivityController::class, 'registrations']);
    Route::get('/my-results', [UserActivityController::class, 'results']);
    Route::get('/achievements', [AchievementController::class, 'index']);
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/events/{event}/register/{category}', [EventController::class, 'register']);
    Route::post('/community-posts', [ContentController::class, 'storeCommunityPost']);
    Route::delete('/community-posts/{post}', [ContentController::class, 'destroyCommunityPost']);
});
