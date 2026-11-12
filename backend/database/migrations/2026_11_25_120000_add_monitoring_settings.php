<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** R-115 and R-116: per-gym reminder days and the inactivity check, with its de-duplication table. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gyms', function (Blueprint $table) {
            // Null keeps the platform default from config/communication.php.
            $table->json('membership_reminder_days')->nullable();
            $table->unsignedSmallInteger('inactivity_days')->default(14);
        });

        Schema::create('inactivity_notices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('membership_id')->constrained()->cascadeOnDelete();
            $table->date('last_activity_on');
            $table->timestamps();
            $table->unique(['membership_id', 'last_activity_on']);
        });

        Schema::table('gym_visits', function (Blueprint $table) {
            $table->index(['gym_id', 'user_id', 'checked_in_at']);
        });
    }

    public function down(): void
    {
        Schema::table('gym_visits', function (Blueprint $table) {
            $table->dropIndex(['gym_id', 'user_id', 'checked_in_at']);
        });
        Schema::dropIfExists('inactivity_notices');
        Schema::table('gyms', function (Blueprint $table) {
            $table->dropColumn(['membership_reminder_days', 'inactivity_days']);
        });
    }
};
