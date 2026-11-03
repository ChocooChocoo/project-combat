<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * R-08 "dynamic RBAC": owners create their own roles instead of ticking
 * permissions per person. A role holds one level per module.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('staff_permissions');

        Schema::create('gym_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->string('name', 60);
            $table->json('permissions'); // module => level
            $table->timestamps();
            $table->unique(['gym_id', 'name']);
        });

        Schema::create('gym_role_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['gym_role_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gym_role_user');
        Schema::dropIfExists('gym_roles');

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
};
