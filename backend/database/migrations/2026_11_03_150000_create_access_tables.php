<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Emailed codes for account confirmation and password reset (R-02, R-04).
        Schema::create('one_time_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('purpose', 20);
            $table->string('code_hash');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamps();
            $table->index(['user_id', 'purpose']);
        });

        // Minimal gym record so roles can be tied to a gym. Phase 2 adds the profile.
        Schema::create('gyms', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('status', 20)->default('pending');
            $table->timestamps();
        });

        // A person's role inside one gym (R-07). One person may hold roles in several gyms.
        Schema::create('gym_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 20);
            $table->timestamps();
            $table->unique(['gym_id', 'user_id', 'role']);
        });

        // Module-by-module access an owner grants an employee (R-08).
        Schema::create('staff_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('module', 40);
            $table->string('level', 20);
            $table->timestamps();
            $table->unique(['gym_id', 'user_id', 'module']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_permissions');
        Schema::dropIfExists('gym_user');
        Schema::dropIfExists('gyms');
        Schema::dropIfExists('one_time_codes');
    }
};
