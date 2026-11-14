<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** R-137: a walk-in visitor who signs in on the public kiosk page. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('walk_ins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 150);
            $table->string('phone', 30);
            $table->string('email')->nullable();
            $table->json('health_answers')->nullable();
            $table->text('health_notes')->nullable();
            $table->json('waivers');
            $table->string('signed_name', 150);
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->dateTime('checked_in_at');
            $table->timestamps();
            $table->index(['gym_id', 'checked_in_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('walk_ins');
    }
};
