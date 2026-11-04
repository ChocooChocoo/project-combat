<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * R-20 and R-21: an approved gym's workspace settings, including the work
 * days leave and availability use, and the rooms sessions can be held in.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gyms', function (Blueprint $table) {
            $table->json('activity_types')->nullable();
            $table->json('working_days')->nullable();
            $table->text('policies')->nullable();
        });

        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['gym_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rooms');

        Schema::table('gyms', function (Blueprint $table) {
            $table->dropColumn(['activity_types', 'working_days', 'policies']);
        });
    }
};
