<?php

use App\Http\Controllers\Admin\GymReviewController;
use App\Http\Controllers\Admin\PlatformStatsController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\DiscoveryController;
use App\Http\Controllers\GeoController;
use App\Http\Controllers\Gym\AthleteRecordController;
use App\Http\Controllers\Gym\EmployeeProfileController;
use App\Http\Controllers\Gym\GymApplicationController;
use App\Http\Controllers\Gym\GymDocumentController;
use App\Http\Controllers\Gym\GymRoleController;
use App\Http\Controllers\Gym\GymSetupController;
use App\Http\Controllers\Gym\HolidayController;
use App\Http\Controllers\Gym\LeaveController;
use App\Http\Controllers\Gym\MembershipController;
use App\Http\Controllers\Gym\PaymentController;
use App\Http\Controllers\Gym\PlanController;
use App\Http\Controllers\Gym\RoomController;
use App\Http\Controllers\Gym\ScheduleController;
use App\Http\Controllers\Gym\ServiceController;
use App\Http\Controllers\Gym\StaffController;
use App\Http\Controllers\Gym\WorkController;
use App\Http\Controllers\GymPreferenceController;
use App\Http\Controllers\MembershipApplicationController;
use App\Http\Controllers\MyMembershipController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PayMongoWebhookController;
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
    Route::post('accept-invite', [AuthController::class, 'acceptInvite']);
});

// R-35: PayMongo confirms payments here. The signature is checked inside; no sign-in.
Route::post('webhooks/paymongo', PayMongoWebhookController::class)->middleware('throttle:60,1');

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

            // Discovery (R-30, R-97, R-98). Recommendations must come before the gym id route.
            Route::get('gyms', [PublicGymController::class, 'index']);
            Route::get('gyms/recommendations', [DiscoveryController::class, 'recommendations']);
            Route::get('gyms/{gym}', [PublicGymController::class, 'show'])->whereNumber('gym');
            Route::get('discovery/options', [DiscoveryController::class, 'options']);
            Route::get('geo/search', [GeoController::class, 'search'])->middleware('throttle:geo');
            Route::get('gym-preferences', [GymPreferenceController::class, 'show']);
            Route::put('gym-preferences', [GymPreferenceController::class, 'update']);

            // Joining a gym (R-31, R-32) and the person's own memberships and online payment (R-35, S-39).
            // These sit outside the gym check: an applicant does not belong to the gym yet.
            Route::get('membership-form', [MembershipApplicationController::class, 'form']);
            Route::post('gyms/{gym}/membership-applications', [MembershipApplicationController::class, 'store'])->whereNumber('gym');
            Route::get('my-memberships', [MyMembershipController::class, 'index']);
            Route::get('my-memberships/{membership}', [MyMembershipController::class, 'show'])->whereNumber('membership');
            Route::post('my-memberships/{membership}/checkout', [MyMembershipController::class, 'checkout'])->whereNumber('membership');
            Route::post('my-memberships/{membership}/checkout/confirm', [MyMembershipController::class, 'confirm'])->whereNumber('membership');

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

                // Workspace setup (R-20). Options are open to any member.
                Route::get('setup', [GymSetupController::class, 'show'])->middleware('module:gym_setup,view');
                Route::put('setup', [GymSetupController::class, 'update'])->middleware('module:gym_setup,manage');
                Route::get('options', [GymSetupController::class, 'options']);

                // Rooms (R-21).
                Route::get('rooms', [RoomController::class, 'index'])->middleware('module:gym_setup,view');
                Route::post('rooms', [RoomController::class, 'store'])->middleware('module:gym_setup,manage');
                Route::put('rooms/{room}', [RoomController::class, 'update'])->whereNumber('room')->middleware('module:gym_setup,manage');
                Route::delete('rooms/{room}', [RoomController::class, 'destroy'])->whereNumber('room')->middleware('module:gym_setup,manage');

                // Staff (R-22 to R-24). Showing a person is open to that person; the controller checks.
                Route::get('staff', [StaffController::class, 'index'])->middleware('module:hr,view');
                Route::post('staff', [StaffController::class, 'store'])->middleware('module:hr,manage');
                Route::get('staff/{user}', [StaffController::class, 'show']);
                Route::put('staff/{user}', [StaffController::class, 'update'])->middleware('module:hr,manage');
                Route::delete('staff/{user}', [StaffController::class, 'destroy'])->middleware('module:hr,manage');
                Route::put('staff/{user}/profile', [EmployeeProfileController::class, 'update']);
                Route::get('staff/{user}/schedule', [ScheduleController::class, 'show']);
                Route::put('staff/{user}/schedule', [ScheduleController::class, 'update'])->middleware('module:hr,manage');

                // Leave (R-25, R-26). Own requests are open; HR sees all. The controller checks.
                Route::get('leave-requests', [LeaveController::class, 'index']);
                Route::post('leave-requests', [LeaveController::class, 'store']);
                Route::get('leave-requests/{leave}', [LeaveController::class, 'show'])->whereNumber('leave');
                Route::get('leave-requests/{leave}/attachment', [LeaveController::class, 'attachment'])->whereNumber('leave');
                Route::post('leave-requests/{leave}/cancel', [LeaveController::class, 'cancel'])->whereNumber('leave');
                Route::post('leave-requests/{leave}/decision', [LeaveController::class, 'decide'])->whereNumber('leave')->middleware('module:hr,manage');
                Route::get('leave-balances', [LeaveController::class, 'balances']);

                // Holidays (R-27).
                Route::get('holidays', [HolidayController::class, 'index']);
                Route::post('holidays', [HolidayController::class, 'store'])->middleware('module:hr,manage');
                Route::delete('holidays/{holiday}', [HolidayController::class, 'destroy'])->whereNumber('holiday')->middleware('module:hr,manage');

                // The employee's own work view.
                Route::get('my-work', [WorkController::class, 'show']);

                // Services (R-28).
                // Viewing is open to gym setup or membership-plan managers, so add-ons can be chosen.
                Route::get('services', [ServiceController::class, 'index']);
                Route::post('services', [ServiceController::class, 'store'])->middleware('module:gym_setup,manage');
                Route::put('services/{service}', [ServiceController::class, 'update'])->whereNumber('service')->middleware('module:gym_setup,manage');
                Route::delete('services/{service}', [ServiceController::class, 'destroy'])->whereNumber('service')->middleware('module:gym_setup,manage');
                Route::post('services/{service}/cover', [ServiceController::class, 'cover'])->whereNumber('service')->middleware('module:gym_setup,manage');
                Route::post('services/{service}/photos', [ServiceController::class, 'storePhoto'])->whereNumber('service')->middleware('module:gym_setup,manage');
                Route::delete('services/{service}/photos/{photo}', [ServiceController::class, 'destroyPhoto'])->whereNumber('service')->whereNumber('photo')->middleware('module:gym_setup,manage');
                Route::post('services/{service}/publish', [ServiceController::class, 'publish'])->whereNumber('service')->middleware('module:gym_setup,manage');
                Route::post('services/{service}/unpublish', [ServiceController::class, 'unpublish'])->whereNumber('service')->middleware('module:gym_setup,manage');

                // Membership plans (R-29).
                Route::get('plans', [PlanController::class, 'index'])->middleware('module:members,view');
                Route::post('plans', [PlanController::class, 'store'])->middleware('module:members,manage');
                Route::put('plans/{plan}', [PlanController::class, 'update'])->whereNumber('plan')->middleware('module:members,manage');
                Route::delete('plans/{plan}', [PlanController::class, 'destroy'])->whereNumber('plan')->middleware('module:members,manage');
                Route::post('plans/{plan}/publish', [PlanController::class, 'publish'])->whereNumber('plan')->middleware('module:members,manage');
                Route::post('plans/{plan}/unpublish', [PlanController::class, 'unpublish'])->whereNumber('plan')->middleware('module:members,manage');

                // Applications and memberships (R-33, R-36). Deciding needs membership approval.
                // Members, coaches and the payments desk all need the list; the controller checks.
                Route::get('memberships', [MembershipController::class, 'index']);
                Route::get('memberships/{membership}', [MembershipController::class, 'show'])->whereNumber('membership')->middleware('module:members,view');
                Route::get('memberships/{membership}/medical-file', [MembershipController::class, 'medicalFile'])->whereNumber('membership');
                Route::post('memberships/{membership}/decision', [MembershipController::class, 'decide'])->whereNumber('membership')->middleware('module:members,approve');
                Route::middleware('module:members,manage')->group(function () {
                    Route::post('memberships/{membership}/renew', [MembershipController::class, 'renew'])->whereNumber('membership');
                    Route::post('memberships/{membership}/freeze', [MembershipController::class, 'freeze'])->whereNumber('membership');
                    Route::post('memberships/{membership}/unfreeze', [MembershipController::class, 'unfreeze'])->whereNumber('membership');
                    Route::post('memberships/{membership}/cancel', [MembershipController::class, 'cancel'])->whereNumber('membership');
                });

                // Payments desk (R-34).
                Route::get('payments', [PaymentController::class, 'index'])->middleware('module:finance,view');
                Route::post('payments', [PaymentController::class, 'store'])->middleware('module:finance,manage');
                Route::post('payments/{payment}/correct', [PaymentController::class, 'correct'])->whereNumber('payment')->middleware('module:finance,manage');

                // Athlete records (R-37 to R-41). Viewing and health checks are in the controller.
                Route::get('athletes/{user}/record', [AthleteRecordController::class, 'show']);
                Route::get('athletes/{user}/medical-file', [AthleteRecordController::class, 'medicalFile']);
                Route::post('athletes/{user}/entries', [AthleteRecordController::class, 'storeEntry'])->middleware('module:training,manage');
                Route::get('my-record', [AthleteRecordController::class, 'mine']);
                // POST as well as PUT: browsers send file uploads as POST.
                Route::put('my-record/health', [AthleteRecordController::class, 'updateMyHealth']);
                Route::post('my-record/health', [AthleteRecordController::class, 'updateMyHealth']);
            });
        });
    });
});
