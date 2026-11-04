<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * R-28 and R-29: the services a gym sells, their photo gallery, the all-inclusive
 * membership plans and their paid add-ons. Preferences feed recommendations (R-98).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gym_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('discipline', 40);
            $table->string('activity_type', 40);
            $table->text('overview');
            $table->string('level', 60);
            $table->unsignedSmallInteger('duration_minutes');
            $table->string('frequency', 100);
            $table->json('included');
            $table->json('bring');
            $table->decimal('price', 12, 2)->nullable();
            $table->boolean('price_included')->default(false);
            $table->string('cover_photo_path')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        Schema::create('gym_service_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->foreignId('gym_service_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->unsignedTinyInteger('sort')->default(0);
            $table->timestamps();
            $table->index(['gym_service_id', 'sort']);
        });

        Schema::create('membership_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('billing_type', 20);
            $table->unsignedSmallInteger('duration_months')->nullable();
            $table->unsignedSmallInteger('session_count')->nullable();
            $table->decimal('total_fee', 12, 2);
            $table->boolean('registration_included')->default(false);
            $table->json('inclusions');
            $table->text('policy_validity');
            $table->text('policy_freezing');
            $table->text('policy_renewal');
            $table->text('policy_cancellation');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        Schema::create('membership_plan_addons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->foreignId('membership_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('gym_service_id')->constrained()->cascadeOnDelete();
            $table->decimal('price', 12, 2);
            $table->timestamps();
            $table->unique(['membership_plan_id', 'gym_service_id']);
        });

        Schema::create('gym_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('discipline', 40)->nullable();
            $table->decimal('min_price', 12, 2)->nullable();
            $table->decimal('max_price', 12, 2)->nullable();
            $table->json('preferred_times')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gym_preferences');
        Schema::dropIfExists('membership_plan_addons');
        Schema::dropIfExists('membership_plans');
        Schema::dropIfExists('gym_service_photos');
        Schema::dropIfExists('gym_services');
    }
};
