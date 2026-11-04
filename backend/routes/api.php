<?php

use App\Http\Controllers\Admin\GymReviewController;
use App\Http\Controllers\Admin\PlatformStatsController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Gym\GymApplicationController;
use App\Http\Controllers\Gym\GymDocumentController;
use App\Http\Controllers\Gym\GymRoleController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicGymController;
use Illuminate\Support\Facades\Route;

// Public sign-up and sign-in. 'auth-public' limits requests per address (see AppServiceProvider).
Route::prefix('auth')->middleware('throttle:auth-public')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('verify', [AuthController::class, 'verify']);
    Route::post('resend-code', [AuthController::class, 'resendCode']);
    Route::post('login', [AuthController::class, 'login']);
    Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('reset-password', [AuthController::class, 'resetPassword']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::get('me', [ProfileController::class, 'show']);
    Route::post('me/first-password', [ProfileController::class, 'setFirstPassword']);

    Route::middleware('password.changed')->group(function () {
        Route::put('me', [ProfileController::class, 'update']);
        Route::post('me/photo', [ProfileController::class, 'updatePhoto']);
        Route::put('me/password', [ProfileController::class, 'changePassword']);

        Route::middleware('verified.email')->group(function () {
            Route::get('notifications', [NotificationController::class, 'index']);
            Route::post('notifications/read-all', [NotificationController::class, 'markAllRead']);
            Route::delete('notifications/read', [NotificationController::class, 'clearRead']);
            Route::post('notifications/{id}/read', [NotificationController::class, 'markRead']);

            Route::get('access/templates', [GymRoleController::class, 'templates']);

            // Owner applications (R-11 to R-15). The policy limits each one to its applicant and the administrator.
            Route::prefix('gym-applications')->group(function () {
                Route::get('requirements', [GymApplicationController::class, 'requirements']);
                Route::get('/', [GymApplicationController::class, 'index']);
                Route::post('/', [GymApplicationController::class, 'store']);
                Route::get('{gym}', [GymApplicationController::class, 'show']);
                Route::put('{gym}', [GymApplicationController::class, 'update']);
                Route::post('{gym}/submit', [GymApplicationController::class, 'submit']);
                Route::post('{gym}/documents', [GymDocumentController::class, 'store']);
                Route::get('{gym}/documents/{document}', [GymDocumentController::class, 'show'])->scopeBindings();
                Route::delete('{gym}/documents/{document}', [GymDocumentController::class, 'destroy'])->scopeBindings();
            });

            // Approved gyms with their tier badge (R-18).
            Route::get('gyms', [PublicGymController::class, 'index']);

            // Platform administrator: review applications and platform numbers (R-16 to R-19).
            Route::prefix('admin')->middleware('admin')->group(function () {
                Route::get('stats', PlatformStatsController::class);
                Route::get('applications', [GymReviewController::class, 'index']);
                Route::get('applications/{gym}', [GymReviewController::class, 'show']);
                Route::put('applications/{gym}/checklist', [GymReviewController::class, 'checklist']);
                Route::post('applications/{gym}/decision', [GymReviewController::class, 'decide']);
            });

            // Everything inside a gym goes through the gym check first (R-09).
            Route::prefix('gyms/{gym}')->middleware('gym')->group(function () {
                // Owner-made roles (R-08). Viewing needs HR view; changing needs HR manage.
                Route::middleware('module:hr,view')->group(function () {
                    Route::get('roles', [GymRoleController::class, 'index']);
                    Route::get('staff/{user}/roles', [GymRoleController::class, 'staffRoles']);
                });
                Route::middleware('module:hr,manage')->group(function () {
                    Route::post('roles', [GymRoleController::class, 'store']);
                    Route::put('roles/{role}', [GymRoleController::class, 'update'])->whereNumber('role');
                    Route::delete('roles/{role}', [GymRoleController::class, 'destroy'])->whereNumber('role');
                    Route::put('staff/{user}/roles', [GymRoleController::class, 'assign']);
                });
            });
        });
    });
});
