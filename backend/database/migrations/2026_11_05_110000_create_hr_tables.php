<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * R-23 to R-27: employee records, weekly schedules, leave requests and
 * balances, and the gym's own holidays beside the national ones.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('job_title', 100)->nullable();
            $table->date('started_on')->nullable();
            // Q-13: a coach may be paid monthly, per session, or both.
            $table->string('pay_basis', 20)->nullable();
            $table->decimal('monthly_rate', 12, 2)->nullable();
            $table->decimal('per_session_rate', 12, 2)->nullable();
            $table->string('coach_photo_path')->nullable();
            $table->text('coach_experience')->nullable();
            $table->string('coach_rank', 100)->nullable();
            $table->string('coach_specialisation', 150)->nullable();
            $table->text('coach_bio')->nullable();
            $table->boolean('coach_public')->default(false);
            $table->timestamps();
            $table->unique(['gym_id', 'user_id']);
        });

        Schema::create('work_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('weekday', 3);
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->timestamps();
            $table->unique(['gym_id', 'user_id', 'weekday']);
        });

        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 40);
            $table->date('starts_on');
            $table->date('ends_on');
            $table->unsignedSmallInteger('working_days')->default(0);
            $table->text('reason');
            $table->string('attachment_path')->nullable();
            $table->string('attachment_name')->nullable();
            $table->string('attachment_mime', 100)->nullable();
            $table->unsignedBigInteger('attachment_size')->nullable();
            $table->string('status', 20)->default('pending');
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('decision_note')->nullable();
            $table->timestamps();
            $table->index(['gym_id', 'user_id', 'status']);
        });

        Schema::create('leave_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 40);
            $table->unsignedSmallInteger('year');
            $table->unsignedSmallInteger('allowance');
            $table->timestamps();
            $table->unique(['gym_id', 'user_id', 'type', 'year']);
        });

        Schema::create('gym_holidays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('name', 120);
            $table->timestamps();
            $table->unique(['gym_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gym_holidays');
        Schema::dropIfExists('leave_balances');
        Schema::dropIfExists('leave_requests');
        Schema::dropIfExists('work_schedules');
        Schema::dropIfExists('employee_profiles');
    }
};
