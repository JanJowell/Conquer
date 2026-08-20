<?php

use App\Http\Controllers\Admin\AnnouncementController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\ContentController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\EBadgeController;
use App\Http\Controllers\Admin\EventController;
use App\Http\Controllers\Admin\EventOperationsController;
use App\Http\Controllers\Admin\NotificationController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\SearchController;
use App\Http\Controllers\Admin\SecurityController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\ProfileController;
use App\Models\Announcement;
use App\Models\Event;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    $events = Event::latest()->take(3)->get();
    $announcements = Announcement::active()->with('event')->latest()->take(5)->get();

    return view('welcome', compact('events', 'announcements'));
})->name('home');

Route::get('/announcements', function () {
    $announcements = Announcement::active()
        ->with('event')
        ->latest('published_at')
        ->latest()
        ->get();

    return view('pages.announcements', compact('announcements'));
})->name('announcements.index');

Route::view('/payments/success', 'pages.payments.success')->name('payments.success');
Route::view('/payments/cancelled', 'pages.payments.cancelled')->name('payments.cancelled');

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
    Route::get('/search', [SearchController::class, 'index'])->name('search');

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/analytics', [DashboardController::class, 'analytics'])
        ->middleware('role:super_admin,executive')
        ->name('analytics');
    Route::get('/reports', [DashboardController::class, 'reports'])
        ->middleware('role:super_admin,executive,event_manager')
        ->name('reports');
    Route::get('/reports/export/{type}', [DashboardController::class, 'export'])
        ->middleware('role:super_admin,executive,event_manager')
        ->name('reports.export');
    Route::get('/feedback-insights', [DashboardController::class, 'feedbackInsights'])
        ->middleware('role:super_admin,executive,content_moderator,event_manager')
        ->name('feedback-insights');
    Route::get('/participants', [EventOperationsController::class, 'participants'])
        ->middleware('role:super_admin,event_manager')
        ->name('participants.index');
    Route::get('/participants/export', [EventOperationsController::class, 'exportParticipants'])
        ->middleware('role:super_admin,event_manager')
        ->name('participants.export');
    Route::patch('/participants/{registration}', [EventOperationsController::class, 'updateParticipant'])
        ->middleware('role:super_admin,event_manager')
        ->name('participants.update');
    Route::get('/payments', [PaymentController::class, 'index'])
        ->middleware('role:super_admin,event_manager')
        ->name('payments.index');
    Route::get('/payments/export', [PaymentController::class, 'export'])
        ->middleware('role:super_admin,event_manager')
        ->name('payments.export');
    Route::patch('/payments/registrations/{registration}', [PaymentController::class, 'update'])
        ->middleware('role:super_admin,event_manager')
        ->name('payments.update');
    Route::get('/check-in', [EventOperationsController::class, 'checkIn'])
        ->middleware('role:super_admin,event_manager')
        ->name('check-in.index');
    Route::patch('/check-in/{registration}', [EventOperationsController::class, 'updateCheckIn'])
        ->middleware('role:super_admin,event_manager')
        ->name('check-in.update');
    Route::get('/results', [EventOperationsController::class, 'results'])
        ->middleware('role:super_admin,event_manager')
        ->name('results.index');
    Route::post('/results', [EventOperationsController::class, 'storeResult'])
        ->middleware('role:super_admin,event_manager')
        ->name('results.store');
    Route::patch('/results/{result}', [EventOperationsController::class, 'updateResult'])
        ->middleware('role:super_admin,event_manager')
        ->name('results.update');
    Route::get('/e-badges', [EBadgeController::class, 'index'])
        ->middleware('role:super_admin,event_manager')
        ->name('e-badges.index');
    Route::post('/e-badges', [EBadgeController::class, 'store'])
        ->middleware('role:super_admin,event_manager')
        ->name('e-badges.store');
    Route::patch('/e-badges/{badge}', [EBadgeController::class, 'update'])
        ->middleware('role:super_admin,event_manager')
        ->name('e-badges.update');
    Route::delete('/e-badges/{badge}', [EBadgeController::class, 'destroy'])
        ->middleware('role:super_admin,event_manager')
        ->name('e-badges.destroy');
    Route::post('/registrations/{registration}/e-badges', [EBadgeController::class, 'issue'])
        ->middleware('role:super_admin,event_manager')
        ->name('e-badges.issue');
    Route::delete('/issued-e-badges/{issuedBadge}', [EBadgeController::class, 'revoke'])
        ->middleware('role:super_admin,event_manager')
        ->name('e-badges.revoke');

    // User Management
    Route::get('/users', [UserController::class, 'index'])
        ->middleware('role:super_admin,executive')
        ->name('users.index');
    Route::resource('users', UserController::class)
        ->except(['index'])
        ->middleware('role:super_admin');
    Route::post('/users/{user}/suspend', [UserController::class, 'suspend'])->middleware('role:super_admin')->name('users.suspend');
    Route::post('/users/{user}/unsuspend', [UserController::class, 'unsuspend'])->middleware('role:super_admin')->name('users.unsuspend');
    Route::post('/users/{user}/ban', [UserController::class, 'ban'])->middleware('role:super_admin')->name('users.ban');
    Route::post('/users/{user}/unban', [UserController::class, 'unban'])->middleware('role:super_admin')->name('users.unban');

    // Event Management
    Route::get('/events', [EventController::class, 'index'])
        ->middleware('role:super_admin,executive,event_manager')
        ->name('events.index');
    Route::resource('events', EventController::class)
        ->except(['index', 'show'])
        ->middleware('role:super_admin,event_manager');
    Route::get('/events/{event}', [EventController::class, 'show'])
        ->middleware('role:super_admin,executive,event_manager')
        ->name('events.show');
    Route::post('/categories/{category}/start', [CategoryController::class, 'start'])
        ->middleware('role:super_admin,event_manager')
        ->name('categories.start');
    Route::resource('categories', CategoryController::class)
        ->middleware('role:super_admin,event_manager')
        ->except(['show']);
    Route::resource('announcements', AnnouncementController::class)
        ->middleware('role:super_admin,content_moderator,event_manager')
        ->except(['show']);
    Route::patch('/announcements/{announcement}/publish', [AnnouncementController::class, 'publish'])
        ->middleware('role:super_admin,content_moderator,event_manager')
        ->name('announcements.publish');
    Route::patch('/announcements/{announcement}/unpublish', [AnnouncementController::class, 'unpublish'])
        ->middleware('role:super_admin,content_moderator,event_manager')
        ->name('announcements.unpublish');

    // Content Management
    Route::prefix('content')->name('content.')->group(function () {
        Route::get('/pending-review', [ContentController::class, 'pendingReview'])->middleware('role:super_admin,content_moderator')->name('pending-review');
        Route::get('/community-posts', [ContentController::class, 'communityPosts'])->middleware('role:super_admin,content_moderator')->name('community-posts');
        Route::get('/community-posts/{post}', [ContentController::class, 'showCommunityPost'])->middleware('role:super_admin,content_moderator')->name('community-posts.show');
        Route::delete('/community-posts/{post}', [ContentController::class, 'deleteCommunityPost'])->middleware('role:super_admin,content_moderator')->name('community-posts.delete');
        Route::post('/community-posts/{post}/restore', [ContentController::class, 'restoreCommunityPost'])->middleware('role:super_admin,content_moderator')->name('community-posts.restore');
        Route::post('/community-posts/{post}/flag', [ContentController::class, 'flagCommunityPost'])->middleware('role:super_admin,content_moderator')->name('community-posts.flag');
        Route::post('/community-posts/{post}/unflag', [ContentController::class, 'unflagCommunityPost'])->middleware('role:super_admin,content_moderator')->name('community-posts.unflag');
        Route::delete('/community-comments/{comment}', [ContentController::class, 'deleteCommunityComment'])->middleware('role:super_admin,content_moderator')->name('community-comments.delete');
        Route::post('/community-comments/{comment}/restore', [ContentController::class, 'restoreCommunityComment'])->middleware('role:super_admin,content_moderator')->name('community-comments.restore');
        Route::post('/community-comments/{comment}/flag', [ContentController::class, 'flagCommunityComment'])->middleware('role:super_admin,content_moderator')->name('community-comments.flag');
        Route::post('/community-comments/{comment}/unflag', [ContentController::class, 'unflagCommunityComment'])->middleware('role:super_admin,content_moderator')->name('community-comments.unflag');

        Route::get('/training-modules', [ContentController::class, 'trainingModules'])->middleware('role:super_admin,content_moderator')->name('training-modules');
        Route::get('/training-modules/create', [ContentController::class, 'createTrainingModule'])->middleware('role:super_admin,content_moderator')->name('training-modules.create');
        Route::post('/training-modules', [ContentController::class, 'storeTrainingModule'])->middleware('role:super_admin,content_moderator')->name('training-modules.store');
        Route::get('/training-modules/{module}', [ContentController::class, 'showTrainingModule'])->middleware('role:super_admin,content_moderator')->name('training-modules.show');
        Route::get('/training-modules/{module}/edit', [ContentController::class, 'editTrainingModule'])->middleware('role:super_admin,content_moderator')->name('training-modules.edit');
        Route::put('/training-modules/{module}', [ContentController::class, 'updateTrainingModule'])->middleware('role:super_admin,content_moderator')->name('training-modules.update');
        Route::delete('/training-modules/{module}', [ContentController::class, 'destroyTrainingModule'])->middleware('role:super_admin,content_moderator')->name('training-modules.destroy');

        Route::get('/checkpoints', [ContentController::class, 'checkpoints'])->middleware('role:super_admin,event_manager')->name('checkpoints');
        Route::get('/checkpoints/create', [ContentController::class, 'createCheckpoint'])->middleware('role:super_admin,event_manager')->name('checkpoints.create');
        Route::post('/checkpoints', [ContentController::class, 'storeCheckpoint'])->middleware('role:super_admin,event_manager')->name('checkpoints.store');
        Route::get('/checkpoints/{checkpoint}/edit', [ContentController::class, 'editCheckpoint'])->middleware('role:super_admin,event_manager')->name('checkpoints.edit');
        Route::put('/checkpoints/{checkpoint}', [ContentController::class, 'updateCheckpoint'])->middleware('role:super_admin,event_manager')->name('checkpoints.update');
        Route::delete('/checkpoints/{checkpoint}', [ContentController::class, 'destroyCheckpoint'])->middleware('role:super_admin,event_manager')->name('checkpoints.destroy');
    });

    // Notifications
    Route::resource('notifications', NotificationController::class)
        ->middleware('role:super_admin,content_moderator,event_manager');
    Route::post('/notifications/{notification}/send-now', [NotificationController::class, 'sendNow'])
        ->middleware('role:super_admin,content_moderator,event_manager')
        ->name('notifications.send-now');

    // Security
    Route::prefix('security')->name('security.')->group(function () {
        Route::get('/dashboard', [SecurityController::class, 'dashboard'])->middleware('role:super_admin')->name('dashboard');
        Route::get('/activity-logs', [SecurityController::class, 'activityLogs'])->middleware('role:super_admin')->name('activity-logs');
        Route::get('/banned-ips', [SecurityController::class, 'bannedIPs'])->middleware('role:super_admin')->name('banned-ips');
        Route::post('/ban-ip', [SecurityController::class, 'banIP'])->middleware('role:super_admin')->name('ban-ip');
        Route::post('/unban-ip/{bannedIP}', [SecurityController::class, 'unbanIP'])->middleware('role:super_admin')->name('unban-ip');
        Route::get('/login-monitoring', [SecurityController::class, 'loginMonitoring'])->middleware('role:super_admin')->name('login-monitoring');
        Route::post('/enforce-2fa', [SecurityController::class, 'enforce2FA'])->middleware('role:super_admin')->name('enforce-2fa');
        Route::get('/data-access-logs', [SecurityController::class, 'dataAccessLogs'])->middleware('role:super_admin')->name('data-access-logs');
    });
});
