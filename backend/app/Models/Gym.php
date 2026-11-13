<?php

namespace App\Models;

use App\Enums\GymPersonType;
use App\Enums\GymStatus;
use App\Policies\GymApplicationPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/** A gym. Until approved, the same row is its owner application (R-11 to R-18). */
#[Fillable([
    'name', 'tier', 'address_line', 'barangay', 'city', 'province', 'zip_code', 'latitude', 'longitude',
    'contact_phone', 'contact_email', 'operating_hours', 'disciplines', 'services', 'facilities',
    'activity_types', 'custom_activity_types', 'working_days', 'policies',
    'booking_approval', 'late_grace_minutes', 'cancel_cutoff_hours', 'session_reminder_minutes',
    'membership_reminder_days', 'inactivity_days', 'attendance_radius_m',
    'payroll_frequency', 'payroll_steps', 'staff_grace_minutes', 'overtime_min_minutes',
])]
#[UsePolicy(GymApplicationPolicy::class)]
class Gym extends Model
{
    public function peopleWithPermission(string $permission): Collection
    {
        return $this->people()->get()->unique('id')
            ->filter(fn (User $person) => $person->hasPermission($this, $permission))->values();
    }

    use HasFactory;

    /** D-24: built-in session types plus the gym's own, keyed custom_<slug>. */
    public function activityTypeOptions(): array
    {
        $own = collect($this->custom_activity_types ?? [])
            ->mapWithKeys(fn (string $label) => [self::customTypeKey($label) => $label]);

        return [...config('offer.activity_types'), ...$own];
    }

    public static function customTypeKey(string $label): string
    {
        return 'custom_'.Str::slug($label, '_');
    }

    /** ponytail: a removed custom type keeps showing as its key in readable form; block removal while in use if that confuses staff. */
    public function activityTypeLabel(?string $type): string
    {
        return $this->activityTypeOptions()[$type] ?? Str::headline(Str::after((string) $type, 'custom_'));
    }

    /** Profile fields an application needs before it can be sent (R-11). */
    public const REQUIRED_PROFILE = [
        'name' => 'gym name',
        'address_line' => 'street address',
        'barangay' => 'barangay',
        'city' => 'city or municipality',
        'province' => 'province',
        'latitude' => 'map location',
        'longitude' => 'map location',
        'contact_phone' => 'contact number',
        'contact_email' => 'contact email',
        'operating_hours' => 'operating hours',
        'disciplines' => 'combat sports offered',
        'services' => 'services',
        'facilities' => 'facilities',
        'tier' => 'tier',
    ];

    /** Q-21: the default working week. The owner can change it in the workspace settings. */
    public const DEFAULT_WORKING_DAYS = ['mon', 'tue', 'wed', 'thu', 'fri'];

    /** R-61: a one-step chain needs only the owner. */
    public const DEFAULT_PAYROLL_STEPS = ['owner'];

    protected function casts(): array
    {
        return [
            'status' => GymStatus::class,
            'tier' => 'integer',
            'approved_tier' => 'integer',
            'latitude' => 'float',
            'longitude' => 'float',
            'operating_hours' => 'array',
            'disciplines' => 'array',
            'services' => 'array',
            'facilities' => 'array',
            'activity_types' => 'array',
            'custom_activity_types' => 'array',
            'working_days' => 'array',
            'booking_approval' => 'boolean',
            'late_grace_minutes' => 'integer',
            'cancel_cutoff_hours' => 'integer',
            'session_reminder_minutes' => 'integer',
            'membership_reminder_days' => 'array',
            'inactivity_days' => 'integer',
            'attendance_radius_m' => 'integer',
            'payroll_steps' => 'array',
            'staff_grace_minutes' => 'integer',
            'overtime_min_minutes' => 'integer',
            'return_items' => 'array',
            'checklist' => 'array',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'trial_ends_at' => 'datetime',
            'paid_until' => 'datetime',
            'subscription_reminded_for' => 'datetime',
        ];
    }

    public function people(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('role')->withTimestamps();
    }

    public function addPerson(User $user, GymPersonType $role): void
    {
        $this->people()->attach($user->id, ['role' => $role->value]);
    }

    public function subscriptionPlan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class);
    }

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applicant_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(GymDocument::class);
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }

    public function employees(): HasMany
    {
        return $this->hasMany(EmployeeProfile::class);
    }

    public function workSchedules(): HasMany
    {
        return $this->hasMany(WorkSchedule::class);
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function leaveBalances(): HasMany
    {
        return $this->hasMany(LeaveBalance::class);
    }

    public function holidays(): HasMany
    {
        return $this->hasMany(GymHoliday::class);
    }

    public function gymServices(): HasMany
    {
        return $this->hasMany(GymService::class);
    }

    public function membershipPlans(): HasMany
    {
        return $this->hasMany(MembershipPlan::class);
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function athleteProfiles(): HasMany
    {
        return $this->hasMany(AthleteProfile::class);
    }

    public function athleteEntries(): HasMany
    {
        return $this->hasMany(AthleteEntry::class);
    }

    public function staffAttendance(): HasMany
    {
        return $this->hasMany(StaffAttendance::class);
    }

    public function overtimeRequests(): HasMany
    {
        return $this->hasMany(OvertimeRequest::class);
    }

    public function performanceNotes(): HasMany
    {
        return $this->hasMany(PerformanceNote::class);
    }

    public function stockItems(): HasMany
    {
        return $this->hasMany(StockItem::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function customerInteractions(): HasMany
    {
        return $this->hasMany(CustomerInteraction::class);
    }

    public function customerFollowUps(): HasMany
    {
        return $this->hasMany(CustomerFollowUp::class);
    }

    public function customerGroups(): HasMany
    {
        return $this->hasMany(CustomerGroup::class);
    }

    public function suppliers(): HasMany
    {
        return $this->hasMany(Supplier::class);
    }

    public function supplyNeeds(): HasMany
    {
        return $this->hasMany(SupplyNeed::class);
    }

    public function purchaseRequests(): HasMany
    {
        return $this->hasMany(PurchaseRequest::class);
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function goodsReceipts(): HasMany
    {
        return $this->hasMany(GoodsReceipt::class);
    }

    public function supplierPayments(): HasMany
    {
        return $this->hasMany(SupplierPayment::class);
    }

    public function payrollRuns(): HasMany
    {
        return $this->hasMany(PayrollRun::class);
    }

    /** Platform rates are reached separately; this is only the gym's own rows. */
    public function statutoryRates(): HasMany
    {
        return $this->hasMany(StatutoryRate::class);
    }

    /** The working week the gym set, or Monday to Friday when it has not set one (Q-21). */
    public function workingDays(): array
    {
        return $this->working_days ?: self::DEFAULT_WORKING_DAYS;
    }

    /** The payroll approval chain, owner last (R-61). */
    public function payrollSteps(): array
    {
        return $this->payroll_steps ?: self::DEFAULT_PAYROLL_STEPS;
    }
}
