<?php

use App\Support\PermissionCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** R-117: a member applies to coach at a gym, with their weekly availability. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coach_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20)->default('pending');
            $table->text('experience');
            $table->string('rank', 100)->nullable();
            $table->string('specialisation', 150)->nullable();
            $table->text('bio')->nullable();
            $table->json('availability');
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('decided_at')->nullable();
            $table->text('decision_reason')->nullable();
            $table->timestamps();
            $table->index(['gym_id', 'status']);
        });

        PermissionCatalog::sync();
    }

    public function down(): void
    {
        Schema::dropIfExists('coach_applications');
    }
};
