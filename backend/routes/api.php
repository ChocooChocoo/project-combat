<?php

use App\Http\Controllers\Admin\AdminPermissionController;
use App\Http\Controllers\Admin\GymReviewController;
use App\Http\Controllers\Admin\PlatformStatsController;
use App\Http\Controllers\Admin\SubscriptionPlanController;
use App\Http\Controllers\Admin\PlatformNoticeController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\DiscoveryController;
use App\Http\Controllers\GeoController;
use App\Http\Controllers\Gym\AthleteRecordController;
use App\Http\Controllers\Gym\AnnouncementController;
use App\Http\Controllers\Gym\AttendanceController;
use App\Http\Controllers\Gym\BookingController;
use App\Http\Controllers\Gym\BudgetController;
use App\Http\Controllers\Gym\CoachCalendarController;
use App\Http\Controllers\Gym\CustomerController;
use App\Http\Controllers\Gym\CustomerGroupController;
use App\Http\Controllers\Gym\DecisionSupportController;
use App\Http\Controllers\Gym\EmployeeProfileController;
use App\Http\Controllers\Gym\ExpenseController;
use App\Http\Controllers\Gym\FinanceReportController;
use App\Http\Controllers\Gym\FollowUpController;
use App\Http\Controllers\Gym\GymApplicationController;
use App\Http\Controllers\Gym\GymDocumentController;
use App\Http\Controllers\Gym\GymRoleController;
use App\Http\Controllers\Gym\GymSetupController;
use App\Http\Controllers\Gym\HolidayController;
use App\Http\Controllers\Gym\IncomeController;
use App\Http\Controllers\Gym\LeaveController;
use App\Http\Controllers\Gym\MembershipController;
use App\Http\Controllers\Gym\MyAttendanceController;
use App\Http\Controllers\Gym\OvertimeController;
use App\Http\Controllers\Gym\PayableController;
use App\Http\Controllers\Gym\PaymentController;
use App\Http\Controllers\Gym\PayrollRunController;
use App\Http\Controllers\Gym\PayrollSettingsController;
use App\Http\Controllers\Gym\SubscriptionController;
use App\Http\Controllers\Gym\PayslipController;
use App\Http\Controllers\Gym\PerformanceNoteController;
use App\Http\Controllers\Gym\PlanController;
use App\Http\Controllers\Gym\PurchaseOrderController;
use App\Http\Controllers\Gym\PurchaseRequestController;
use App\Http\Controllers\Gym\ReceivableController as GymReceivableController;
use App\Http\Controllers\Gym\ReceivingController;
use App\Http\Controllers\Gym\RestockController;
use App\Http\Controllers\Gym\RoomController;
use App\Http\Controllers\Gym\SaleController;
use App\Http\Controllers\Gym\ScheduleController;
use App\Http\Controllers\Gym\ServiceController;
use App\Http\Controllers\Gym\SessionController;
use App\Http\Controllers\Gym\SessionNoteController;
use App\Http\Controllers\Gym\StaffAttendanceController;
use App\Http\Controllers\Gym\StaffController;
use App\Http\Controllers\Gym\StatutoryRateController;
use App\Http\Controllers\Gym\StockItemController;
use App\Http\Controllers\Gym\StockMovementController;
use App\Http\Controllers\Gym\SupplierController;
use App\Http\Controllers\Gym\SupplyNeedController;
use App\Http\Controllers\Gym\WorkController;
use App\Http\Controllers\GymPreferenceController;
use App\Http\Controllers\MembershipApplicationController;
use App\Http\Controllers\MyBookingController;
use App\Http\Controllers\MemberDashboardController;
use App\Http\Controllers\MyMembershipController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PayMongoWebhookController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicGymController;
use App\Http\Controllers\PublicLandingController;
use App\Http\Controllers\ReceivableController;
use App\Support\CaviteLocations;
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

// The landing page is for visitors who have no account yet. It reads only approved
// gyms and what they published, the same information the public gym page shows.
Route::get('public/landing', PublicLandingController::class)->middleware('throttle:60,1');
Route::get('gyms/{gym}', [PublicGymController::class, 'show'])->whereNumber('gym')->middleware('throttle:60,1');

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
            Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount']);
            Route::post('notifications/read-all', [NotificationController::class, 'markAllRead']);
            Route::delete('notifications/read', [NotificationController::class, 'clearRead']);
            Route::post('notifications/{id}/read', [NotificationController::class, 'markRead']);

            Route::get('permissions/catalog', [GymRoleController::class, 'catalog']);

            // Owner applications (R-11 to R-15). The policy limits each one to its applicant and the administrator.
            Route::prefix('gym-applications')->middleware('account:owner')->group(function () {
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
            Route::get('gyms/recommendations', [DiscoveryController::class, 'recommendations'])->middleware('account:member');
            Route::get('discovery/options', [DiscoveryController::class, 'options']);
            Route::get('geo/search', [GeoController::class, 'search'])->middleware('throttle:geo');
            Route::get('locations/cavite', fn () => response()->json(CaviteLocations::all()));
            Route::get('gym-preferences', [GymPreferenceController::class, 'show'])->middleware('account:member');
            Route::put('gym-preferences', [GymPreferenceController::class, 'update'])->middleware('account:member');

            // Joining a gym (R-31, R-32) and the person's own memberships and online payment (R-35, S-39).
            // These sit outside the gym check: an applicant does not belong to the gym yet.
            // The form metadata is readable by any signed-in account: owners use the same health
            // questions when reviewing applications and member records. Only starting an
            // application below stays member-only.
            Route::get('membership-form', [MembershipApplicationController::class, 'form']);
            Route::post('gyms/{gym}/membership-applications', [MembershipApplicationController::class, 'store'])->whereNumber('gym')->middleware('account:member');
            Route::get('my-memberships', [MyMembershipController::class, 'index']);
            Route::get('my-balance', [ReceivableController::class, 'mine']);
            Route::get('my-bookings', [MyBookingController::class, 'index']);
            // P07: the person's own dashboard numbers across their gyms.
            Route::get('me/dashboard', MemberDashboardController::class);
            Route::post('my-bookings/{booking}/checkout', [MyBookingController::class, 'checkout'])->whereNumber('booking');
            Route::post('my-bookings/{booking}/checkout/confirm', [MyBookingController::class, 'confirm'])->whereNumber('booking');
            Route::get('my-memberships/{membership}', [MyMembershipController::class, 'show'])->whereNumber('membership');
            Route::post('my-memberships/{membership}/checkout', [MyMembershipController::class, 'checkout'])->whereNumber('membership');
            Route::post('my-memberships/{membership}/checkout/confirm', [MyMembershipController::class, 'confirm'])->whereNumber('membership');

            // R-62: released payslips are the employee's own, outside the gym check.
            Route::get('me/payslips', [PayslipController::class, 'index']);
            Route::get('me/payslips/{line}', [PayslipController::class, 'show'])->whereNumber('line');

            // Platform administrator: review applications and platform numbers (R-16 to R-19).
            Route::prefix('admin')->middleware('admin')->group(function () {
                Route::get('notices', [PlatformNoticeController::class, 'index']);
                Route::post('notices', [PlatformNoticeController::class, 'store']);
                Route::get('permissions', [AdminPermissionController::class, 'index']);
                Route::post('permissions', [AdminPermissionController::class, 'store']);
                Route::put('permissions/{permission}', [AdminPermissionController::class, 'update']);
                Route::post('permissions/{permission}/toggle-status', [AdminPermissionController::class, 'toggleStatus']);
                Route::delete('permissions/{permission}', [AdminPermissionController::class, 'destroy']);
                Route::get('stats', PlatformStatsController::class);
                Route::get('applications', [GymReviewController::class, 'index']);
                Route::get('applications/{gym}', [GymReviewController::class, 'show']);
                Route::put('applications/{gym}/checklist', [GymReviewController::class, 'checklist']);
                Route::post('applications/{gym}/decision', [GymReviewController::class, 'decide']);
                // R-102 to R-104: subscription plans and the trial length.
                Route::get('subscription-plans', [SubscriptionPlanController::class, 'index']);
                Route::post('subscription-plans', [SubscriptionPlanController::class, 'store']);
                Route::put('subscription-plans/{plan}', [SubscriptionPlanController::class, 'update']);
                Route::post('subscription-plans/{plan}/toggle-retired', [SubscriptionPlanController::class, 'toggleRetired']);
                Route::put('subscription-settings', [SubscriptionPlanController::class, 'updateSettings']);
            });

            // Everything inside a gym goes through the gym check first (R-09).
            Route::prefix('gyms/{gym}')->middleware('gym')->group(function () {
                // R-105, R-107: the owner's own plan. The owner check is inside the controller.
                Route::get('subscription', [SubscriptionController::class, 'show']);
                Route::post('subscription/checkout', [SubscriptionController::class, 'checkout']);
                Route::post('subscription/confirm', [SubscriptionController::class, 'confirm']);
                Route::get('decision-support/options', [DecisionSupportController::class, 'options'])->middleware('permission:decision_support.general.view');
                Route::get('decision-support/attendance', [DecisionSupportController::class, 'attendance'])->middleware('permission:decision_support.general.view');
                Route::get('decision-support/athletes', [DecisionSupportController::class, 'athletes'])->middleware('permission:decision_support.general.view');
                Route::get('decision-support/services', [DecisionSupportController::class, 'services'])->middleware('permission:decision_support.general.view');
                Route::get('decision-support/memberships', [DecisionSupportController::class, 'memberships'])->middleware('permission:decision_support.general.view');
                Route::get('decision-support/customers', [DecisionSupportController::class, 'customers'])->middleware('permission:decision_support.general.view');
                Route::get('decision-support/employees', [DecisionSupportController::class, 'employees'])->middleware('permission:decision_support.general.view');
                Route::get('decision-support/money', [DecisionSupportController::class, 'money'])->middleware('permission:decision_support.general.view');
                Route::get('decision-support/stock', [DecisionSupportController::class, 'stock'])->middleware('permission:decision_support.general.view');
                Route::get('decision-support/trends', [DecisionSupportController::class, 'trends'])->middleware('permission:decision_support.general.view');
                Route::get('decision-support/forecast', [DecisionSupportController::class, 'forecast'])->middleware('permission:decision_support.general.view');
                Route::get('decision-support/problems', [DecisionSupportController::class, 'problems'])->middleware('permission:decision_support.general.view');
                Route::get('decision-support/sustainability', [DecisionSupportController::class, 'sustainability'])->middleware('permission:decision_support.general.view');
                Route::get('announcements/options', [AnnouncementController::class, 'options'])->middleware('permission:announcements.general.manage');
                Route::get('announcements', [AnnouncementController::class, 'index'])->middleware('permission:announcements.general.view');
                Route::post('announcements', [AnnouncementController::class, 'store'])->middleware('permission:announcements.general.manage');

                // Owner-made roles (R-08). Viewing needs HR view; changing needs HR manage.
                Route::middleware('permission:hr.roles.view')->group(function () {
                    Route::get('roles', [GymRoleController::class, 'index']);
                    Route::get('staff/{user}/roles', [GymRoleController::class, 'staffRoles']);
                });
                Route::middleware('permission:hr.roles.manage')->group(function () {
                    Route::post('roles/{role}/toggle-status', [GymRoleController::class, 'toggleStatus'])->whereNumber('role');
                    Route::post('roles', [GymRoleController::class, 'store']);
                    Route::put('roles/{role}', [GymRoleController::class, 'update'])->whereNumber('role');
                    Route::delete('roles/{role}', [GymRoleController::class, 'destroy'])->whereNumber('role');
                    Route::put('staff/{user}/roles', [GymRoleController::class, 'assign']);
                });

                // Workspace setup (R-20). Options are open to any member.
                Route::get('setup', [GymSetupController::class, 'show'])->middleware('permission:gym_setup.settings.view');
                Route::put('setup', [GymSetupController::class, 'update'])->middleware('permission:gym_setup.settings.manage');
                Route::get('options', [GymSetupController::class, 'options']);

                // Rooms (R-21).
                Route::get('rooms', [RoomController::class, 'index'])->middleware('permission:gym_setup.rooms.view');
                Route::post('rooms', [RoomController::class, 'store'])->middleware('permission:gym_setup.rooms.manage');
                Route::put('rooms/{room}', [RoomController::class, 'update'])->whereNumber('room')->middleware('permission:gym_setup.rooms.manage');
                Route::delete('rooms/{room}', [RoomController::class, 'destroy'])->whereNumber('room')->middleware('permission:gym_setup.rooms.manage');

                // Sessions (R-42, R-43). Options must come before the occurrence id.
                // R-44, R-47 to R-50: members book without staff module access.
                Route::get('timetable', [BookingController::class, 'timetable']);
                // R-52 to R-55: members show codes; only the training desk redeems them.
                Route::get('my-check-in-code', [AttendanceController::class, 'code'])->middleware('throttle:30,1');
                Route::get('check-ins/members', [AttendanceController::class, 'members'])->middleware('permission:training.check_ins.manage');
                Route::get('check-ins', [AttendanceController::class, 'today'])->middleware('permission:training.check_ins.manage');
                Route::post('check-ins', [AttendanceController::class, 'store'])->middleware(['permission:training.check_ins.manage', 'throttle:60,1']);
                Route::get('attendance', [AttendanceController::class, 'index'])->middleware('permission:training.attendance.view');
                Route::put('bookings/{booking}/attendance', [AttendanceController::class, 'attendance'])->whereNumber('booking')->middleware('permission:training.attendance.manage');
                Route::post('bookings/{booking}/check-out', [AttendanceController::class, 'checkOut'])->whereNumber('booking')->middleware('permission:training.attendance.manage');
                Route::post('sessions/{session}/notes', [SessionNoteController::class, 'store'])->whereNumber('session');
                Route::get('timetable/{session}', [BookingController::class, 'show'])->whereNumber('session');
                Route::post('timetable/{session}/book', [BookingController::class, 'book'])->whereNumber('session');
                Route::get('coaches/{user}/open-slots', [CoachCalendarController::class, 'slots'])->whereNumber('user');
                Route::get('coaches/{user}/calendar', [CoachCalendarController::class, 'calendar'])->whereNumber('user');
                Route::post('private-bookings', [CoachCalendarController::class, 'bookPrivate']);
                Route::get('bookings', [BookingController::class, 'index'])->middleware('permission:training.bookings.view');
                Route::post('bookings/{booking}/cancel', [BookingController::class, 'cancel'])->whereNumber('booking');
                Route::post('bookings/{booking}/decision', [BookingController::class, 'decide'])->whereNumber('booking')->middleware('permission:training.bookings.approve');
                Route::get('sessions/{session}/roster', [BookingController::class, 'roster'])->whereNumber('session')->middleware('permission:training.sessions.view');
                Route::get('sessions/options', [SessionController::class, 'options'])->middleware('permission:training.sessions.view');
                Route::get('sessions', [SessionController::class, 'index'])->middleware('permission:training.sessions.view');
                Route::get('sessions/{session}', [SessionController::class, 'show'])->whereNumber('session')->middleware('permission:training.sessions.view');
                Route::post('sessions', [SessionController::class, 'store'])->middleware('permission:training.sessions.manage');
                Route::put('sessions/{session}', [SessionController::class, 'update'])->whereNumber('session')->middleware('permission:training.sessions.manage');
                Route::post('sessions/{session}/cancel', [SessionController::class, 'cancel'])->whereNumber('session')->middleware('permission:training.sessions.manage');
                Route::get('sessions/{session}/free-coaches', [SessionController::class, 'freeCoaches'])->whereNumber('session')->middleware('permission:training.sessions.view');

                // Staff (R-22 to R-24). Showing a person is open to that person; the controller checks.
                Route::get('staff', [StaffController::class, 'index'])->middleware('permission:hr.staff.view');
                Route::post('staff', [StaffController::class, 'store'])->middleware('permission:hr.staff.manage');
                Route::get('staff/{user}', [StaffController::class, 'show'])->middleware('plan:hr');
                Route::put('staff/{user}', [StaffController::class, 'update'])->middleware('permission:hr.staff.manage');
                Route::delete('staff/{user}', [StaffController::class, 'destroy'])->middleware('permission:hr.staff.manage');
                Route::put('staff/{user}/profile', [EmployeeProfileController::class, 'update'])->middleware('plan:hr');
                Route::get('staff/{user}/schedule', [ScheduleController::class, 'show'])->middleware('plan:hr');
                Route::put('staff/{user}/schedule', [ScheduleController::class, 'update'])->middleware('permission:hr.schedules.manage');

                // Leave (R-25, R-26). Own requests are open; HR sees all. The controller checks.
                Route::get('leave-requests', [LeaveController::class, 'index'])->middleware('plan:hr');
                Route::post('leave-requests', [LeaveController::class, 'store'])->middleware('plan:hr');
                Route::get('leave-requests/{leave}', [LeaveController::class, 'show'])->whereNumber('leave')->middleware('plan:hr');
                Route::get('leave-requests/{leave}/attachment', [LeaveController::class, 'attachment'])->whereNumber('leave')->middleware('plan:hr');
                Route::post('leave-requests/{leave}/cancel', [LeaveController::class, 'cancel'])->whereNumber('leave')->middleware('plan:hr');
                Route::post('leave-requests/{leave}/decision', [LeaveController::class, 'decide'])->whereNumber('leave')->middleware('permission:hr.leave.manage');
                Route::get('leave-balances', [LeaveController::class, 'balances'])->middleware('plan:hr');

                // Holidays (R-27).
                Route::get('holidays', [HolidayController::class, 'index']);
                Route::post('holidays', [HolidayController::class, 'store'])->middleware('permission:hr.holidays.manage');
                Route::delete('holidays/{holiday}', [HolidayController::class, 'destroy'])->whereNumber('holiday')->middleware('permission:hr.holidays.manage');

                // Staff attendance and overtime (R-56, R-57). Own records are open; the controller checks.
                Route::get('staff-attendance', [StaffAttendanceController::class, 'index'])->middleware('permission:hr.attendance.view');
                Route::get('staff-attendance/employees', [StaffAttendanceController::class, 'employees'])->middleware('permission:hr.attendance.view');
                Route::post('staff-attendance', [StaffAttendanceController::class, 'store'])->middleware('permission:hr.attendance.manage');
                Route::put('staff-attendance/{entry}', [StaffAttendanceController::class, 'update'])->whereNumber('entry')->middleware('permission:hr.attendance.manage');
                Route::get('staff-attendance/{entry}/changes', [StaffAttendanceController::class, 'changes'])->whereNumber('entry')->middleware('permission:hr.attendance.view');
                Route::get('my-attendance', [MyAttendanceController::class, 'index'])->middleware('plan:hr');
                Route::post('my-attendance/clock-in', [MyAttendanceController::class, 'clockIn'])->middleware('plan:hr');
                Route::post('my-attendance/clock-out', [MyAttendanceController::class, 'clockOut'])->middleware('plan:hr');
                Route::put('my-attendance/{entry}/explanation', [MyAttendanceController::class, 'explain'])->whereNumber('entry')->middleware('plan:hr');
                Route::get('overtime-requests', [OvertimeController::class, 'index'])->middleware('permission:hr.attendance.view,hr.overtime.approve');
                Route::post('my-overtime-requests', [OvertimeController::class, 'store'])->middleware('plan:hr');
                Route::post('overtime-requests/{overtime}/decision', [OvertimeController::class, 'decide'])->whereNumber('overtime')->middleware('permission:hr.overtime.approve');

                // Performance notes (R-58). Never shown to the employee they describe.
                Route::get('staff/{user}/performance-notes', [PerformanceNoteController::class, 'index'])->middleware('permission:hr.performance.view');
                Route::post('staff/{user}/performance-notes', [PerformanceNoteController::class, 'store'])->middleware('permission:hr.performance.manage');

                // Payroll (R-59 to R-62). The owner check for the last step is inside the workflow.
                Route::get('payroll/runs', [PayrollRunController::class, 'index'])->middleware('permission:payroll.runs.view');
                Route::post('payroll/runs', [PayrollRunController::class, 'store'])->middleware('permission:payroll.runs.manage');
                Route::get('payroll/runs/{run}', [PayrollRunController::class, 'show'])->whereNumber('run')->middleware('permission:payroll.runs.view');
                Route::post('payroll/runs/{run}/compute', [PayrollRunController::class, 'compute'])->whereNumber('run')->middleware('permission:payroll.runs.manage');
                Route::post('payroll/runs/{run}/submit', [PayrollRunController::class, 'submit'])->whereNumber('run')->middleware('permission:payroll.runs.manage');
                Route::post('payroll/runs/{run}/decision', [PayrollRunController::class, 'decide'])->whereNumber('run')->middleware('permission:payroll.runs.approve');
                Route::delete('payroll/runs/{run}', [PayrollRunController::class, 'destroy'])->whereNumber('run')->middleware('permission:payroll.runs.manage');
                Route::get('payroll/settings', [PayrollSettingsController::class, 'show'])->middleware('permission:payroll.runs.view');
                Route::put('payroll/settings', [PayrollSettingsController::class, 'update'])->middleware('plan:payroll');
                Route::get('payroll/rates', [StatutoryRateController::class, 'index'])->middleware('permission:payroll.runs.view');
                Route::post('payroll/rates', [StatutoryRateController::class, 'store'])->middleware('permission:payroll.rates.manage');

                // The employee's own work view.
                Route::get('my-work', [WorkController::class, 'show'])->middleware('plan:hr');

                // Services (R-28).
                // Viewing is open to gym setup or membership-plan managers, so add-ons can be chosen.
                Route::get('services', [ServiceController::class, 'index']);
                Route::post('services', [ServiceController::class, 'store'])->middleware('permission:gym_setup.services.manage');
                Route::put('services/{service}', [ServiceController::class, 'update'])->whereNumber('service')->middleware('permission:gym_setup.services.manage');
                Route::delete('services/{service}', [ServiceController::class, 'destroy'])->whereNumber('service')->middleware('permission:gym_setup.services.manage');
                Route::post('services/{service}/cover', [ServiceController::class, 'cover'])->whereNumber('service')->middleware('permission:gym_setup.services.manage');
                Route::post('services/{service}/photos', [ServiceController::class, 'storePhoto'])->whereNumber('service')->middleware('permission:gym_setup.services.manage');
                Route::delete('services/{service}/photos/{photo}', [ServiceController::class, 'destroyPhoto'])->whereNumber('service')->whereNumber('photo')->middleware('permission:gym_setup.services.manage');
                Route::post('services/{service}/publish', [ServiceController::class, 'publish'])->whereNumber('service')->middleware('permission:gym_setup.services.manage');
                Route::post('services/{service}/unpublish', [ServiceController::class, 'unpublish'])->whereNumber('service')->middleware('permission:gym_setup.services.manage');

                // Membership plans (R-29).
                Route::get('plans', [PlanController::class, 'index'])->middleware('permission:members.plans.view');
                Route::post('plans', [PlanController::class, 'store'])->middleware('permission:members.plans.manage');
                Route::put('plans/{plan}', [PlanController::class, 'update'])->whereNumber('plan')->middleware('permission:members.plans.manage');
                Route::delete('plans/{plan}', [PlanController::class, 'destroy'])->whereNumber('plan')->middleware('permission:members.plans.manage');
                Route::post('plans/{plan}/publish', [PlanController::class, 'publish'])->whereNumber('plan')->middleware('permission:members.plans.manage');
                Route::post('plans/{plan}/unpublish', [PlanController::class, 'unpublish'])->whereNumber('plan')->middleware('permission:members.plans.manage');

                // Applications and memberships (R-33, R-36). Deciding needs membership approval.
                // Members, coaches and the payments desk all need the list; the controller checks.
                Route::get('memberships', [MembershipController::class, 'index']);
                Route::get('memberships/{membership}', [MembershipController::class, 'show'])->whereNumber('membership')->middleware('permission:members.memberships.view');
                Route::get('memberships/{membership}/medical-file', [MembershipController::class, 'medicalFile'])->whereNumber('membership');
                Route::post('memberships/{membership}/decision', [MembershipController::class, 'decide'])->whereNumber('membership')->middleware('permission:members.memberships.approve');
                Route::middleware('permission:members.memberships.manage')->group(function () {
                    Route::post('memberships/{membership}/renew', [MembershipController::class, 'renew'])->whereNumber('membership');
                    Route::post('memberships/{membership}/freeze', [MembershipController::class, 'freeze'])->whereNumber('membership');
                    Route::post('memberships/{membership}/unfreeze', [MembershipController::class, 'unfreeze'])->whereNumber('membership');
                    Route::post('memberships/{membership}/cancel', [MembershipController::class, 'cancel'])->whereNumber('membership');
                });

                // Payments desk (R-34).
                Route::get('payments', [PaymentController::class, 'index'])->middleware('permission:finance.payments.view');
                Route::get('payments/owed-bookings', [PaymentController::class, 'owedBookings'])->middleware('permission:finance.payments.view');
                Route::post('payments', [PaymentController::class, 'store'])->middleware('permission:finance.payments.manage');
                Route::post('payments/{payment}/correct', [PaymentController::class, 'correct'])->whereNumber('payment')->middleware('permission:finance.payments.manage');

                // Finance core (R-63 to R-65): income, desk sales, expenses and receivables.
                Route::get('finance/income', [IncomeController::class, 'index'])->middleware('permission:finance.payments.view');
                Route::post('finance/sales', [SaleController::class, 'store'])->middleware('permission:finance.payments.manage');
                Route::get('finance/expenses', [ExpenseController::class, 'index'])->middleware('permission:finance.expenses.view');
                Route::post('finance/expenses', [ExpenseController::class, 'store'])->middleware('permission:finance.expenses.manage');
                Route::post('finance/expenses/{expense}/correct', [ExpenseController::class, 'correct'])->whereNumber('expense')->middleware('permission:finance.expenses.manage');
                Route::get('finance/receivables', [GymReceivableController::class, 'index'])->middleware('permission:finance.receivables.view');

                // Budgets and sustainability reports (R-67, R-68).
                Route::get('finance/budgets', [BudgetController::class, 'index'])->middleware('permission:finance.budgets.view');
                Route::put('finance/budgets', [BudgetController::class, 'update'])->middleware('permission:finance.budgets.manage');
                Route::get('finance/reports/cash-flow', [FinanceReportController::class, 'cashFlow'])->middleware('permission:finance.reports.view');
                Route::get('finance/reports/income-expense', [FinanceReportController::class, 'incomeExpense'])->middleware('permission:finance.reports.view');
                Route::get('finance/reports/payroll', [FinanceReportController::class, 'payroll'])->middleware('permission:finance.reports.view');
                Route::get('finance/reports/payouts', [FinanceReportController::class, 'payouts'])->middleware('permission:finance.reports.view');

                // Stock (R-72 to R-74). Quantity changes only through movements.
                Route::get('stock-items', [StockItemController::class, 'index'])->middleware('permission:inventory.items.view');
                Route::get('stock-items/recipients', [StockMovementController::class, 'recipients'])->middleware('permission:inventory.movements.manage');
                Route::post('stock-items', [StockItemController::class, 'store'])->middleware('permission:inventory.items.manage');
                Route::get('stock-items/{item}', [StockItemController::class, 'show'])->whereNumber('item')->middleware('permission:inventory.items.view');
                Route::put('stock-items/{item}', [StockItemController::class, 'update'])->whereNumber('item')->middleware('permission:inventory.items.manage');
                Route::post('stock-items/{item}/archive', [StockItemController::class, 'archive'])->whereNumber('item')->middleware('permission:inventory.items.manage');
                Route::get('stock-items/{item}/movements', [StockMovementController::class, 'index'])->whereNumber('item')->middleware('permission:inventory.items.view');
                Route::post('stock-items/{item}/movements', [StockMovementController::class, 'store'])->whereNumber('item')->middleware('permission:inventory.movements.manage');

                // Procurement (R-75 to R-79, R-66): restock, approvals, suppliers, orders and payables.
                Route::get('restock', [RestockController::class, 'index'])->middleware('permission:procurement.requests.view,inventory.items.view');
                Route::get('supply-needs', [SupplyNeedController::class, 'index'])->middleware('permission:procurement.requests.view');
                Route::post('supply-needs', [SupplyNeedController::class, 'store'])->middleware('permission:procurement.requests.manage');
                Route::delete('supply-needs/{need}', [SupplyNeedController::class, 'destroy'])->whereNumber('need')->middleware('permission:procurement.requests.manage');
                Route::get('purchase-requests', [PurchaseRequestController::class, 'index'])->middleware('permission:procurement.requests.view');
                Route::post('purchase-requests', [PurchaseRequestController::class, 'store'])->middleware('permission:procurement.requests.manage');
                Route::post('purchase-requests/{purchaseRequest}/decision', [PurchaseRequestController::class, 'decide'])->whereNumber('purchaseRequest')->middleware('permission:procurement.requests.approve');
                Route::post('purchase-requests/{purchaseRequest}/cancel', [PurchaseRequestController::class, 'cancel'])->whereNumber('purchaseRequest')->middleware('permission:procurement.requests.manage');
                Route::get('suppliers', [SupplierController::class, 'index'])->middleware('permission:procurement.suppliers.view');
                Route::post('suppliers', [SupplierController::class, 'store'])->middleware('permission:procurement.suppliers.manage');
                Route::put('suppliers/{supplier}', [SupplierController::class, 'update'])->whereNumber('supplier')->middleware('permission:procurement.suppliers.manage');
                Route::get('purchase-orders', [PurchaseOrderController::class, 'index'])->middleware('permission:procurement.orders.view');
                Route::get('purchase-orders/{order}', [PurchaseOrderController::class, 'show'])->whereNumber('order')->middleware('permission:procurement.orders.view');
                Route::post('purchase-orders', [PurchaseOrderController::class, 'store'])->middleware('permission:procurement.orders.manage');
                Route::post('purchase-orders/direct', [PurchaseOrderController::class, 'direct'])->middleware('permission:procurement.orders.manage');
                Route::post('purchase-orders/{order}/cancel', [PurchaseOrderController::class, 'cancel'])->whereNumber('order')->middleware('permission:procurement.orders.manage');
                Route::post('purchase-orders/{order}/close', [PurchaseOrderController::class, 'closeShort'])->whereNumber('order')->middleware('permission:procurement.orders.manage');
                Route::post('purchase-orders/{order}/receipts', [ReceivingController::class, 'store'])->whereNumber('order')->middleware('permission:procurement.receipts.manage');
                Route::get('finance/payables', [PayableController::class, 'index'])->middleware('permission:finance.payables.view');
                Route::get('finance/payables/{supplier}', [PayableController::class, 'show'])->whereNumber('supplier')->middleware('permission:finance.payables.view');
                Route::post('finance/supplier-payments', [PayableController::class, 'pay'])->middleware('permission:finance.payables.manage');

                // CRM (R-69 to R-71). Records, follow-ups and groups.
                Route::get('customers/staff-choices', [CustomerController::class, 'staffChoices'])->middleware('permission:customers.records.view');
                Route::get('customers/linkable-memberships', [CustomerController::class, 'linkableMemberships'])->middleware('permission:customers.records.view');
                Route::get('customers', [CustomerController::class, 'index'])->middleware('permission:customers.records.view');
                Route::get('customers/{customer}', [CustomerController::class, 'show'])->whereNumber('customer')->middleware('permission:customers.records.view');
                Route::post('customers', [CustomerController::class, 'store'])->middleware('permission:customers.records.manage');
                Route::put('customers/{customer}', [CustomerController::class, 'update'])->whereNumber('customer')->middleware('permission:customers.records.manage');
                Route::post('customers/{customer}/interactions', [CustomerController::class, 'addInteraction'])->whereNumber('customer')->middleware('permission:customers.records.manage');
                Route::post('interactions/{interaction}/resolve', [CustomerController::class, 'resolveInteraction'])->whereNumber('interaction')->middleware('permission:customers.records.manage');
                Route::get('follow-ups', [FollowUpController::class, 'index'])->middleware('permission:customers.records.view');
                Route::post('customers/{customer}/follow-ups', [FollowUpController::class, 'store'])->whereNumber('customer')->middleware('permission:customers.records.manage');
                Route::put('follow-ups/{followUp}', [FollowUpController::class, 'update'])->whereNumber('followUp')->middleware('permission:customers.records.manage');
                Route::post('customers/{customer}/link-membership', [CustomerController::class, 'linkMembership'])->whereNumber('customer')->middleware('permission:customers.records.manage');
                Route::get('customer-groups', [CustomerGroupController::class, 'index'])->middleware('permission:customers.records.view');
                Route::post('customer-groups', [CustomerGroupController::class, 'store'])->middleware('permission:customers.groups.manage');
                Route::put('customer-groups/{group}', [CustomerGroupController::class, 'update'])->whereNumber('group')->middleware('permission:customers.groups.manage');
                Route::delete('customer-groups/{group}', [CustomerGroupController::class, 'destroy'])->whereNumber('group')->middleware('permission:customers.groups.manage');
                Route::put('customer-groups/{group}/members', [CustomerGroupController::class, 'syncMembers'])->whereNumber('group')->middleware('permission:customers.groups.manage');

                // Athlete records (R-37 to R-41). Viewing and health checks are in the controller.
                Route::get('athletes/{user}/record', [AthleteRecordController::class, 'show']);
                Route::get('athletes/{user}/medical-file', [AthleteRecordController::class, 'medicalFile']);
                Route::post('athletes/{user}/entries', [AthleteRecordController::class, 'storeEntry'])->middleware('permission:training.athletes.manage');
                Route::get('my-record', [AthleteRecordController::class, 'mine']);
                // POST as well as PUT: browsers send file uploads as POST.
                Route::put('my-record/health', [AthleteRecordController::class, 'updateMyHealth']);
                Route::post('my-record/health', [AthleteRecordController::class, 'updateMyHealth']);
            });
        });
    });
});
