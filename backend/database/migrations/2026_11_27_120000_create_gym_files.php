<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** R-134, R-135: the gym's cover photo, gallery photos and policy PDFs. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gyms', function (Blueprint $table) {
            $table->string('cover_photo_path')->nullable();
        });

        Schema::create('gym_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 20);
            $table->string('path');
            $table->string('original_name');
            $table->unsignedBigInteger('size');
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
            $table->index(['gym_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gym_files');

        Schema::table('gyms', function (Blueprint $table) {
            $table->dropColumn('cover_photo_path');
        });
    }
};
