<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// (R-133) Expiry date and browser-read name flags on verification files.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gym_documents', function (Blueprint $table) {
            $table->date('expires_on')->nullable();
            $table->json('ocr_flags')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('gym_documents', function (Blueprint $table) {
            $table->dropColumn(['expires_on', 'ocr_flags']);
        });
    }
};
