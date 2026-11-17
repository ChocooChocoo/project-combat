<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** (R-146) A member's coach application carries a resume, a valid ID, a medical clearance and optional certifications. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coach_applications', function (Blueprint $table) {
            $table->string('resume_path')->nullable();
            $table->string('resume_name')->nullable();
            $table->string('certifications_path')->nullable();
            $table->string('certifications_name')->nullable();
            $table->string('valid_id_path')->nullable();
            $table->string('valid_id_name')->nullable();
            $table->string('medical_clearance_path')->nullable();
            $table->string('medical_clearance_name')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('coach_applications', function (Blueprint $table) {
            $table->dropColumn([
                'resume_path', 'resume_name', 'certifications_path', 'certifications_name',
                'valid_id_path', 'valid_id_name', 'medical_clearance_path', 'medical_clearance_name',
            ]);
        });
    }
};
