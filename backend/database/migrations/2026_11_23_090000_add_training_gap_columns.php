<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// D-35: athlete level for booking checks. D-24: the gym's own session types.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('athlete_profiles', function (Blueprint $table) {
            $table->string('level', 20)->nullable()->after('current_rank');
        });
        Schema::table('gyms', function (Blueprint $table) {
            $table->json('custom_activity_types')->nullable()->after('activity_types');
        });
    }

    public function down(): void
    {
        Schema::table('athlete_profiles', fn (Blueprint $table) => $table->dropColumn('level'));
        Schema::table('gyms', fn (Blueprint $table) => $table->dropColumn('custom_activity_types'));
    }
};
