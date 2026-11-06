<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * R-64: money out. One append-only row per cost, with an optional source key so
 * payroll and purchasing can post the same source only once.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->string('category', 40);
            $table->decimal('amount', 12, 2);
            $table->date('incurred_on');
            $table->string('method', 20)->nullable();
            $table->string('note', 255)->nullable();
            $table->string('source_type', 30)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->foreignId('corrects_id')->nullable()->constrained('expenses')->nullOnDelete();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['source_type', 'source_id', 'category']);
            $table->index(['gym_id', 'incurred_on']);
            $table->index(['gym_id', 'category', 'incurred_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
