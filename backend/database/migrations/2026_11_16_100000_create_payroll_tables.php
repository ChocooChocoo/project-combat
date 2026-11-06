<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** R-59 to R-62: payroll runs, lines, line items, approvals and statutory rates. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('status', 20)->default('draft');
            $table->json('steps')->nullable();
            $table->unsignedTinyInteger('current_step')->default(0);
            $table->unsignedSmallInteger('round')->default(1);
            $table->foreignId('prepared_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->dateTime('computed_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('released_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('released_at')->nullable();
            $table->boolean('self_released')->default(false);
            $table->decimal('gross_total', 14, 2)->default(0);
            $table->decimal('employee_statutory_total', 14, 2)->default(0);
            $table->decimal('tax_total', 14, 2)->default(0);
            $table->decimal('net_total', 14, 2)->default(0);
            $table->decimal('employer_statutory_total', 14, 2)->default(0);
            $table->timestamps();

            $table->unique(['gym_id', 'starts_on', 'ends_on']);
            $table->index(['gym_id', 'status']);
        });

        Schema::create('payroll_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('job_title', 100)->nullable();
            $table->string('pay_basis', 20)->nullable();
            $table->string('employment_type', 20)->default('employee');
            $table->decimal('monthly_rate', 14, 2)->nullable();
            $table->decimal('per_session_rate', 14, 2)->nullable();
            $table->decimal('daily_rate', 14, 2)->nullable();
            $table->decimal('minute_rate', 14, 2)->nullable();

            $table->unsignedSmallInteger('scheduled_days')->default(0);
            $table->unsignedSmallInteger('days_worked')->default(0);
            $table->unsignedSmallInteger('days_absent')->default(0);
            $table->unsignedSmallInteger('paid_leave_days')->default(0);
            $table->unsignedSmallInteger('unpaid_leave_days')->default(0);
            $table->unsignedSmallInteger('holiday_days')->default(0);
            $table->unsignedInteger('late_minutes')->default(0);
            $table->unsignedInteger('undertime_minutes')->default(0);
            $table->unsignedInteger('overtime_minutes')->default(0);
            $table->unsignedSmallInteger('sessions_count')->default(0);

            $table->decimal('base_pay', 14, 2)->default(0);
            $table->decimal('attendance_deduction', 14, 2)->default(0);
            $table->decimal('holiday_premium', 14, 2)->default(0);
            $table->decimal('overtime_pay', 14, 2)->default(0);
            $table->decimal('session_pay', 14, 2)->default(0);
            $table->decimal('gross_pay', 14, 2)->default(0);
            $table->decimal('sss_employee', 14, 2)->default(0);
            $table->decimal('philhealth_employee', 14, 2)->default(0);
            $table->decimal('pagibig_employee', 14, 2)->default(0);
            $table->decimal('tax', 14, 2)->default(0);
            $table->decimal('net_pay', 14, 2)->default(0);
            $table->decimal('sss_employer', 14, 2)->default(0);
            $table->decimal('ec_employer', 14, 2)->default(0);
            $table->decimal('philhealth_employer', 14, 2)->default(0);
            $table->decimal('pagibig_employer', 14, 2)->default(0);

            $table->json('warnings')->nullable();
            $table->json('statutory_rate_ids')->nullable();
            $table->timestamps();

            $table->unique(['payroll_run_id', 'user_id']);
            $table->index(['gym_id', 'user_id']);
        });

        Schema::create('payroll_line_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_line_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 40);
            $table->string('label', 150);
            $table->decimal('quantity', 12, 2)->nullable();
            $table->decimal('rate', 14, 2)->nullable();
            $table->decimal('amount', 14, 2);
            $table->json('ref')->nullable();
            $table->timestamps();

            $table->index('payroll_line_id');
        });

        Schema::create('payroll_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_run_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('round');
            $table->unsignedTinyInteger('step_index');
            $table->string('step_key', 30);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('decision', 20);
            $table->text('reason')->nullable();
            $table->dateTime('decided_at');
            $table->timestamps();

            $table->unique(['payroll_run_id', 'round', 'step_index']);
        });

        // No gym global scope: platform rates have a null gym_id and each gym's
        // own rows are selected explicitly with forGym().
        Schema::create('statutory_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('kind', 30);
            $table->date('effective_from');
            $table->json('brackets');
            $table->string('source', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['kind', 'effective_from']);
            $table->index(['gym_id', 'kind', 'effective_from']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('statutory_rates');
        Schema::dropIfExists('payroll_approvals');
        Schema::dropIfExists('payroll_line_items');
        Schema::dropIfExists('payroll_lines');
        Schema::dropIfExists('payroll_runs');
    }
};
