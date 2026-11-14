<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// R-132: when an admin first opened the file.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gym_documents', function (Blueprint $table) {
            $table->dateTime('admin_viewed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('gym_documents', function (Blueprint $table) {
            $table->dropColumn('admin_viewed_at');
        });
    }
};
