<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** (R-147) Proof files attached to an injury report: a medical certificate or other supporting documents. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('injury_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->foreignId('injury_id')->constrained('injuries')->cascadeOnDelete();
            $table->string('path');
            $table->string('original_name');
            $table->string('mime', 100);
            $table->unsignedInteger('size')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index('injury_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('injury_files');
    }
};
