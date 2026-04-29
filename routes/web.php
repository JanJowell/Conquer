<?php

use App\Models\Announcement;
use App\Models\Event;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Auth\ForgotPasswordController;

Route::get('/', function () {
    $events = Event::latest()->take(3)->get();
    $announcements = Announcement::where('is_published', true)->latest()->take(5)->get();

    return view('welcome', compact('events', 'announcements'));
})->name('home');

Route::post('/admin/password/email', [ForgotPasswordController::class, 'store'])
    ->middleware('guest')
    ->name('admin.password.email');

Route::middleware(['auth', 'admin'])->group(function () {
    Route::get('/dashboard', function () {
        return redirect()->route('admin.dashboard');
    })->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::patch('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password.update');
    Route::post('/profile/avatar', [ProfileController::class, 'updateAvatar'])->name('profile.avatar');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/search', [\App\Http\Controllers\Admin\SearchController::class, 'index'])->name('search');

    // Dashboard
    Route::get('/dashboard', [\App\Http\Controllers\Admin\DashboardController::class, 'index'])->name('dashboard');
    Route::get('/analytics', [\App\Http\Controllers\Admin\DashboardController::class, 'analytics'])
        ->middleware('role:super_admin,executive')
        ->name('analytics');
    Route::get('/reports', [\App\Http\Controllers\Admin\DashboardController::class, 'reports'])
        ->middleware('role:super_admin,executive,event_manager')
        ->name('reports');
    Route::get('/reports/export/{type}', [\App\Http\Controllers\Admin\DashboardController::class, 'export'])
        ->middleware('role:super_admin,executive,event_manager')
        ->name('reports.export');
    Route::get('/feedback-insights', [\App\Http\Controllers\Admin\DashboardController::class, 'feedbackInsights'])
        ->middleware('role:super_admin,executive,content_moderator,event_manager')
        ->name('feedback-insights');
    Route::get('/participants', [\App\Http\Controllers\Admin\EventOperationsController::class, 'participants'])
        ->middleware('role:super_admin,event_manager')
        ->name('participants.index');
    Route::patch('/participants/{registration}', [\App\Http\Controllers\Admin\EventOperationsController::class, 'updateParticipant'])
        ->middleware('role:super_admin,event_manager')
        ->name('participants.update');
    Route::get('/check-in', [\App\Http\Controllers\Admin\EventOperationsController::class, 'checkIn'])
        ->middleware('role:super_admin,event_manager')
        ->name('check-in.index');
    Route::patch('/check-in/{registration}', [\App\Http\Controllers\Admin\EventOperationsController::class, 'updateCheckIn'])
        ->middleware('role:super_admin,event_manager')
        ->name('check-in.update');
    Route::get('/results', [\App\Http\Controllers\Admin\EventOperationsController::class, 'results'])
        ->middleware('role:super_admin,event_manager')
        ->name('results.index');
    Route::post('/results', [\App\Http\Controllers\Admin\EventOperationsController::class, 'storeResult'])
        ->middleware('role:super_admin,event_manager')
        ->name('results.store');
    Route::patch('/results/{result}', [\App\Http\Controllers\Admin\EventOperationsController::class, 'updateResult'])
        ->middleware('role:super_admin,event_manager')
        ->name('results.update');
    
    // User Management
    Route::get('/users', [\App\Http\Controllers\Admin\UserController::class, 'index'])
        ->middleware('role:super_admin,executive')
        ->name('users.index');
    Route::resource('users', \App\Http\Controllers\Admin\UserController::class)
        ->except(['index'])
        ->middleware('role:super_admin');
    Route::post('/users/{user}/suspend', [\App\Http\Controllers\Admin\UserController::class, 'suspend'])->middleware('role:super_admin')->name('users.suspend');
    Route::post('/users/{user}/unsuspend', [\App\Http\Controllers\Admin\UserController::class, 'unsuspend'])->middleware('role:super_admin')->name('users.unsuspend');
    Route::post('/users/{user}/ban', [\App\Http\Controllers\Admin\UserController::class, 'ban'])->middleware('role:super_admin')->name('users.ban');
    Route::post('/users/{user}/unban', [\App\Http\Controllers\Admin\UserController::class, 'unban'])->middleware('role:super_admin')->name('users.unban');
    
    // Event Management
    Route::get('/events', [\App\Http\Controllers\Admin\EventController::class, 'index'])
        ->middleware('role:super_admin,executive,event_manager')
        ->name('events.index');
    Route::resource('events', \App\Http\Controllers\Admin\EventController::class)
        ->except(['index'])
        ->middleware('role:super_admin,event_manager');
    Route::resource('categories', \App\Http\Controllers\Admin\CategoryController::class)
        ->middleware('role:super_admin,event_manager')
        ->except(['show', 'edit', 'update']);
    Route::resource('announcements', \App\Http\Controllers\Admin\AnnouncementController::class)
        ->middleware('role:super_admin,content_moderator,event_manager')
        ->except(['show', 'edit', 'update']);
    
    // Content Management
    Route::prefix('content')->name('content.')->group(function () {
        Route::get('/community-posts', [\App\Http\Controllers\Admin\ContentController::class, 'communityPosts'])->middleware('role:super_admin,content_moderator')->name('community-posts');
        Route::delete('/community-posts/{post}', [\App\Http\Controllers\Admin\ContentController::class, 'deleteCommunityPost'])->middleware('role:super_admin,content_moderator')->name('community-posts.delete');
        Route::post('/community-posts/{post}/restore', [\App\Http\Controllers\Admin\ContentController::class, 'restoreCommunityPost'])->middleware('role:super_admin,content_moderator')->name('community-posts.restore');
        Route::post('/community-posts/{post}/flag', [\App\Http\Controllers\Admin\ContentController::class, 'flagCommunityPost'])->middleware('role:super_admin,content_moderator')->name('community-posts.flag');
        Route::post('/community-posts/{post}/unflag', [\App\Http\Controllers\Admin\ContentController::class, 'unflagCommunityPost'])->middleware('role:super_admin,content_moderator')->name('community-posts.unflag');
        
        Route::get('/training-modules', [\App\Http\Controllers\Admin\ContentController::class, 'trainingModules'])->middleware('role:super_admin,content_moderator')->name('training-modules');
        Route::get('/training-modules/create', [\App\Http\Controllers\Admin\ContentController::class, 'createTrainingModule'])->middleware('role:super_admin,content_moderator')->name('training-modules.create');
        Route::post('/training-modules', [\App\Http\Controllers\Admin\ContentController::class, 'storeTrainingModule'])->middleware('role:super_admin,content_moderator')->name('training-modules.store');
        Route::get('/training-modules/{module}/edit', [\App\Http\Controllers\Admin\ContentController::class, 'editTrainingModule'])->middleware('role:super_admin,content_moderator')->name('training-modules.edit');
        Route::put('/training-modules/{module}', [\App\Http\Controllers\Admin\ContentController::class, 'updateTrainingModule'])->middleware('role:super_admin,content_moderator')->name('training-modules.update');
        Route::delete('/training-modules/{module}', [\App\Http\Controllers\Admin\ContentController::class, 'destroyTrainingModule'])->middleware('role:super_admin,content_moderator')->name('training-modules.destroy');
        
        Route::get('/checkpoints', [\App\Http\Controllers\Admin\ContentController::class, 'checkpoints'])->middleware('role:super_admin,event_manager')->name('checkpoints');
        Route::get('/checkpoints/create', [\App\Http\Controllers\Admin\ContentController::class, 'createCheckpoint'])->middleware('role:super_admin,event_manager')->name('checkpoints.create');
        Route::post('/checkpoints', [\App\Http\Controllers\Admin\ContentController::class, 'storeCheckpoint'])->middleware('role:super_admin,event_manager')->name('checkpoints.store');
        Route::get('/checkpoints/{checkpoint}/edit', [\App\Http\Controllers\Admin\ContentController::class, 'editCheckpoint'])->middleware('role:super_admin,event_manager')->name('checkpoints.edit');
        Route::put('/checkpoints/{checkpoint}', [\App\Http\Controllers\Admin\ContentController::class, 'updateCheckpoint'])->middleware('role:super_admin,event_manager')->name('checkpoints.update');
        Route::delete('/checkpoints/{checkpoint}', [\App\Http\Controllers\Admin\ContentController::class, 'destroyCheckpoint'])->middleware('role:super_admin,event_manager')->name('checkpoints.destroy');
    });
    
    // Notifications
    Route::resource('notifications', \App\Http\Controllers\Admin\NotificationController::class)
        ->middleware('role:super_admin,content_moderator,event_manager');
    Route::post('/notifications/{notification}/send-now', [\App\Http\Controllers\Admin\NotificationController::class, 'sendNow'])
        ->middleware('role:super_admin,content_moderator,event_manager')
        ->name('notifications.send-now');
    
    // Security
    Route::prefix('security')->name('security.')->group(function () {
        Route::get('/dashboard', [\App\Http\Controllers\Admin\SecurityController::class, 'dashboard'])->middleware('role:super_admin')->name('dashboard');
        Route::get('/activity-logs', [\App\Http\Controllers\Admin\SecurityController::class, 'activityLogs'])->middleware('role:super_admin')->name('activity-logs');
        Route::get('/banned-ips', [\App\Http\Controllers\Admin\SecurityController::class, 'bannedIPs'])->middleware('role:super_admin')->name('banned-ips');
        Route::post('/ban-ip', [\App\Http\Controllers\Admin\SecurityController::class, 'banIP'])->middleware('role:super_admin')->name('ban-ip');
        Route::post('/unban-ip/{bannedIP}', [\App\Http\Controllers\Admin\SecurityController::class, 'unbanIP'])->middleware('role:super_admin')->name('unban-ip');
        Route::get('/login-monitoring', [\App\Http\Controllers\Admin\SecurityController::class, 'loginMonitoring'])->middleware('role:super_admin')->name('login-monitoring');
        Route::post('/enforce-2fa', [\App\Http\Controllers\Admin\SecurityController::class, 'enforce2FA'])->middleware('role:super_admin')->name('enforce-2fa');
        Route::get('/data-access-logs', [\App\Http\Controllers\Admin\SecurityController::class, 'dataAccessLogs'])->middleware('role:super_admin')->name('data-access-logs');
    });
});
