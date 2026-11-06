<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** R-56, R-57, R-58: staff attendance, overtime requests and performance notes. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_attendance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('work_date');
            $table->time('scheduled_start')->nullable();
            $table->time('scheduled_end')->nullable();
            $table->dateTime('clock_in_at')->nullable();
            $table->dateTime('clock_out_at')->nullable();
            $table->string('status', 20)->default('present');
            $table->unsignedSmallInteger('late_minutes')->default(0);
            $table->unsignedSmallInteger('undertime_minutes')->default(0);
            $table->unsignedSmallInteger('worked_minutes')->default(0);
            $table->boolean('missing_clock_out')->default(false);
            $table->string('source', 20)->default('self');
            $table->text('explanation')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['gym_id', 'user_id', 'work_date']);
            $table->index(['gym_id', 'work_date']);
        });

        Schema::create('staff_attendance_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_attendance_id')->constrained('staff_attendance')->cascadeOnDelete();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('before');
            $table->json('after');
            $table->string('reason', 255);
            $table->timestamps();

            $table->index('staff_attendance_id');
        });

        Schema::create('overtime_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('work_date');
            $table->foreignId('staff_attendance_id')->nullable()->constrained('staff_attendance')->nullOnDelete();
            $table->string('kind', 20);
            $table->unsignedSmallInteger('requested_minutes');
            $table->unsignedSmallInteger('approved_minutes')->nullable();
            $table->text('reason');
            $table->string('status', 20)->default('pending');
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('decided_at')->nullable();
            $table->text('decision_note')->nullable();
            $table->timestamps();

            $table->index(['gym_id', 'status']);
            $table->index(['gym_id', 'user_id', 'work_date']);
        });

        Schema::create('performance_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('noted_on');
            $table->string('kind', 20);
            $table->text('body');
            $table->timestamps();

            $table->index(['gym_id', 'user_id', 'noted_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('performance_notes');
        Schema::dropIfExists('overtime_requests');
        Schema::dropIfExists('staff_attendance_changes');
        Schema::dropIfExists('staff_attendance');
    }
};
