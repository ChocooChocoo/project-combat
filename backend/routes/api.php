<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Gym\GymRoleController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
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
