<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * R-67: one planned amount per gym, kind, category and month. The month is
 * stored as its first day so a month has one key on both SQLite and MySQL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 20);
            $table->string('category', 40);
            $table->date('month');
            $table->decimal('amount', 12, 2);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['gym_id', 'kind', 'category', 'month']);
            $table->index(['gym_id', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budgets');
    }
};
