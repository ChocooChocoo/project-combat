<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * R-59, R-60: one employee's frozen snapshot for one run. The money fields are
 * plain decimals around the calculator's integer centavos.
 */
#[Fillable([
    'payroll_run_id', 'user_id', 'name', 'job_title', 'pay_basis', 'employment_type',
    'monthly_rate', 'per_session_rate', 'daily_rate', 'minute_rate',
    'scheduled_days', 'days_worked', 'days_absent', 'paid_leave_days', 'unpaid_leave_days',
    'holiday_days', 'late_minutes', 'undertime_minutes', 'overtime_minutes', 'sessions_count',
    'base_pay', 'attendance_deduction', 'holiday_premium', 'overtime_pay', 'session_pay', 'gross_pay',
    'sss_employee', 'philhealth_employee', 'pagibig_employee', 'tax', 'net_pay',
    'sss_employer', 'ec_employer', 'philhealth_employer', 'pagibig_employer',
    'warnings', 'statutory_rate_ids',
])]
class PayrollLine extends Model
{
    use BelongsToGym;

    protected function casts(): array
    {
        return [
            'monthly_rate' => 'decimal:2',
            'per_session_rate' => 'decimal:2',
            'daily_rate' => 'decimal:2',
            'minute_rate' => 'decimal:2',
            'scheduled_days' => 'integer',
            'days_worked' => 'integer',
            'days_absent' => 'integer',
            'paid_leave_days' => 'integer',
            'unpaid_leave_days' => 'integer',
            'holiday_days' => 'integer',
            'late_minutes' => 'integer',
            'undertime_minutes' => 'integer',
            'overtime_minutes' => 'integer',
            'sessions_count' => 'integer',
            'base_pay' => 'decimal:2',
            'attendance_deduction' => 'decimal:2',
            'holiday_premium' => 'decimal:2',
            'overtime_pay' => 'decimal:2',
            'session_pay' => 'decimal:2',
            'gross_pay' => 'decimal:2',
            'sss_employee' => 'decimal:2',
            'philhealth_employee' => 'decimal:2',
            'pagibig_employee' => 'decimal:2',
            'tax' => 'decimal:2',
            'net_pay' => 'decimal:2',
            'sss_employer' => 'decimal:2',
            'ec_employer' => 'decimal:2',
            'philhealth_employer' => 'decimal:2',
            'pagibig_employer' => 'decimal:2',
            'warnings' => 'array',
            'statutory_rate_ids' => 'array',
        ];
    }

    /** No global scope: the payslip route runs outside a gym and must still find it. */
    public function run(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class, 'payroll_run_id')->withoutGlobalScope('gym');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PayrollLineItem::class)->orderBy('id');
    }
}
