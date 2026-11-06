<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** R-69 to R-71: CRM records, interaction history, follow-ups and groups. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('email', 150)->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('source', 20);
            $table->text('inquiry')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('membership_id')->nullable()->constrained('memberships')->nullOnDelete();
            $table->dateTime('linked_at')->nullable();
            $table->string('status', 20)->default('open');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['gym_id', 'email']);
            $table->index(['gym_id', 'user_id']);
            $table->index(['gym_id', 'assigned_to']);
            $table->index(['gym_id', 'status']);
        });

        Schema::create('customer_interactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 20);
            $table->string('channel', 20);
            $table->text('body');
            $table->dateTime('occurred_at');
            $table->foreignId('recorded_by')->constrained('users')->cascadeOnDelete();
            $table->dateTime('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('resolution')->nullable();
            $table->timestamps();
            $table->index(['customer_id', 'occurred_at']);
        });

        Schema::create('customer_follow_ups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assigned_to')->constrained('users')->cascadeOnDelete();
            $table->dateTime('due_at');
            $table->text('note');
            $table->string('status', 20)->default('open');
            $table->dateTime('done_at')->nullable();
            $table->foreignId('done_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('outcome')->nullable();
            $table->dateTime('reminded_at')->nullable();
            $table->timestamps();
            $table->index(['gym_id', 'assigned_to', 'status', 'due_at']);
            $table->index(['customer_id', 'status']);
        });

        Schema::create('customer_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->text('description')->nullable();
            $table->timestamps();
            $table->unique(['gym_id', 'name']);
        });

        Schema::create('customer_group_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['customer_group_id', 'customer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_group_members');
        Schema::dropIfExists('customer_groups');
        Schema::dropIfExists('customer_follow_ups');
        Schema::dropIfExists('customer_interactions');
        Schema::dropIfExists('customers');
    }
};
