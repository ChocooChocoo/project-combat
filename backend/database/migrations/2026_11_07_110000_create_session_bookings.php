<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** R-48 to R-50: one reusable booking per athlete and occurrence. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('session_bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->foreignId('training_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('membership_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 20);
            $table->dateTime('waitlisted_at', 6)->nullable();
            $table->decimal('fee', 12, 2)->default(0);
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('decided_at')->nullable();
            $table->text('decision_reason')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('cancelled_at')->nullable();
            // R-52 to R-54: attendance will use the same booking, not a second roster.
            $table->dateTime('check_in_at')->nullable();
            $table->dateTime('check_out_at')->nullable();
            $table->string('attendance', 20)->nullable();
            $table->foreignId('attendance_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('attendance_method', 20)->nullable();
            $table->timestamps();
            $table->unique(['training_session_id', 'user_id']);
            $table->index(['gym_id', 'status']);
            $table->index(['training_session_id', 'status', 'waitlisted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_bookings');
    }
};
