<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** R-42, R-43: sessions and the gym's booking rules for Phase 5. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gyms', function (Blueprint $table) {
            $table->boolean('booking_approval')->default(false);
            $table->unsignedSmallInteger('late_grace_minutes')->default(10);
            $table->unsignedSmallInteger('cancel_cutoff_hours')->default(2);
        });

        Schema::create('training_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->foreignId('gym_service_id')->nullable()->constrained()->nullOnDelete();
            $table->string('series_id', 36)->nullable()->index();
            $table->string('type', 30);
            $table->string('name', 120);
            $table->string('discipline', 40)->nullable();
            $table->string('level', 20);
            $table->foreignId('coach_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('room_id')->constrained()->restrictOnDelete();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->unsignedSmallInteger('capacity');
            $table->json('allowed_plan_ids')->nullable();
            $table->text('content')->nullable();
            $table->text('requirements')->nullable();
            $table->json('bring')->nullable();
            $table->decimal('fee', 12, 2)->nullable();
            $table->boolean('needs_approval')->nullable();
            $table->string('status', 20)->default('scheduled');
            $table->text('cancel_reason')->nullable();
            $table->dateTime('flagged_at')->nullable();
            $table->string('flag_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['gym_id', 'starts_at']);
            $table->index(['coach_id', 'starts_at']);
            $table->index(['room_id', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_sessions');
        Schema::table('gyms', fn (Blueprint $table) => $table->dropColumn(['booking_approval', 'late_grace_minutes', 'cancel_cutoff_hours']));
    }
};
