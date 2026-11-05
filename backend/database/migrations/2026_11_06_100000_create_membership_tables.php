<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * R-31 to R-41: membership applications and their life, payments (desk and
 * PayMongo), membership history, and the athlete record.
 */
return new class extends Migration
{
    public function up(): void
    {
        // R-36: the two plan rules the system enforces. The written policies stay for people to read.
        Schema::table('membership_plans', function (Blueprint $table) {
            $table->unsignedSmallInteger('max_freeze_days')->nullable()->after('policy_cancellation');
            $table->boolean('refundable')->default(false)->after('max_freeze_days');
        });

        // One row is the application and, once approved, the membership itself (R-31, R-33, R-36).
        Schema::create('memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('membership_plan_id')->constrained()->restrictOnDelete();
            $table->string('status', 20);
            $table->json('emergency_contacts');
            $table->text('background')->nullable();
            $table->string('current_rank', 150)->nullable();
            $table->text('training_history')->nullable();
            $table->json('health_answers');
            $table->text('health_notes')->nullable();
            $table->string('medical_file_path')->nullable();
            $table->string('medical_file_name')->nullable();
            $table->string('medical_file_mime', 100)->nullable();
            $table->unsignedBigInteger('medical_file_size')->nullable();
            $table->json('waivers');
            $table->string('signature_name', 150);
            $table->date('signed_on');
            $table->decimal('amount_charged', 12, 2)->default(0);
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->unsignedSmallInteger('sessions_remaining')->nullable();
            $table->unsignedSmallInteger('freeze_days_used')->default(0);
            $table->date('frozen_until')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('decision_reason')->nullable();
            $table->string('pending_checkout_id')->nullable();
            $table->timestamps();
            $table->index(['gym_id', 'user_id', 'status']);
        });

        // R-34, R-35: every payment is its own entry. Corrections and refunds are new, negative entries.
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->foreignId('membership_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('method', 20);
            $table->string('receipt_number', 40)->nullable()->unique();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at');
            $table->text('note')->nullable();
            $table->foreignId('corrects_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->string('paymongo_checkout_id')->nullable()->unique();
            $table->string('paymongo_payment_id')->nullable();
            $table->timestamps();
            $table->index(['gym_id', 'user_id']);
        });

        // R-36: the history of each membership, which the athlete can see.
        Schema::create('membership_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->foreignId('membership_id')->constrained()->cascadeOnDelete();
            $table->string('type', 30);
            $table->foreignId('by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamps();
        });

        // R-33, R-40: the athlete profile made on approval, filled in from the application.
        Schema::create('athlete_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->json('emergency_contacts');
            $table->text('background')->nullable();
            $table->string('current_rank', 150)->nullable();
            $table->text('training_history')->nullable();
            $table->json('health_answers');
            $table->text('health_notes')->nullable();
            $table->string('medical_file_path')->nullable();
            $table->string('medical_file_name')->nullable();
            $table->string('medical_file_mime', 100)->nullable();
            $table->unsignedBigInteger('medical_file_size')->nullable();
            $table->timestamps();
            $table->unique(['gym_id', 'user_id']);
        });

        // R-37 to R-39: promotions, assessments, competitions and achievements in one list.
        Schema::create('athlete_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            $table->string('discipline', 60)->nullable();
            $table->string('title', 150);
            $table->string('division', 100)->nullable();
            $table->string('result', 150)->nullable();
            $table->date('occurred_on');
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['gym_id', 'user_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('athlete_entries');
        Schema::dropIfExists('athlete_profiles');
        Schema::dropIfExists('membership_events');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('memberships');

        Schema::table('membership_plans', function (Blueprint $table) {
            $table->dropColumn(['max_freeze_days', 'refundable']);
        });
    }
};
