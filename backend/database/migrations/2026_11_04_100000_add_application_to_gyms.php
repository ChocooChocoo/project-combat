<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * R-11 to R-18: a gym row is also its owner application. The applicant joins
 * gym_user as owner only when the application is approved.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gyms', function (Blueprint $table) {
            $table->foreignId('applicant_id')->nullable()->after('id')->constrained('users')->nullOnDelete();
            // Chosen tier and approved tier stay apart, so a later tier upgrade (Q-16) needs no data rework.
            $table->unsignedTinyInteger('tier')->nullable()->after('status');
            $table->unsignedTinyInteger('approved_tier')->nullable()->after('tier');
            $table->string('address_line')->nullable();
            $table->string('barangay')->nullable();
            $table->string('city')->nullable();
            $table->string('province')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('contact_phone', 30)->nullable();
            $table->string('contact_email')->nullable();
            $table->json('operating_hours')->nullable();
            $table->json('disciplines')->nullable();
            $table->json('services')->nullable();
            $table->json('facilities')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->text('decision_reason')->nullable();
            $table->json('return_items')->nullable();
            $table->json('checklist')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('status', 20)->default('draft')->change();
            $table->index('status');
        });

        DB::table('gyms')->where('status', 'pending')->update(['status' => 'draft']);

        // Verification files (R-15). Stored on the private disk, never in a public folder.
        Schema::create('gym_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->string('type', 40);
            $table->string('path');
            $table->string('original_name');
            $table->string('mime', 100);
            $table->unsignedBigInteger('size');
            $table->timestamps();
            $table->index(['gym_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gym_documents');

        Schema::table('gyms', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropConstrainedForeignId('applicant_id');
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn([
                'tier', 'approved_tier', 'address_line', 'barangay', 'city', 'province', 'latitude', 'longitude',
                'contact_phone', 'contact_email', 'operating_hours', 'disciplines', 'services', 'facilities',
                'submitted_at', 'decision_reason', 'return_items', 'checklist', 'reviewed_at',
            ]);
            $table->string('status', 20)->default('pending')->change();
        });

        DB::table('gyms')->where('status', 'draft')->update(['status' => 'pending']);
    }
};
