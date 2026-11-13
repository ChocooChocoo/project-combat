<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** R-122: a clock-in distance limit on the gym and the place saved on each clock-in. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gyms', function (Blueprint $table) {
            $table->unsignedSmallInteger('attendance_radius_m')->nullable();
        });

        Schema::table('staff_attendance', function (Blueprint $table) {
            $table->decimal('clock_in_latitude', 10, 7)->nullable();
            $table->decimal('clock_in_longitude', 10, 7)->nullable();
            $table->unsignedInteger('clock_in_distance_m')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('staff_attendance', function (Blueprint $table) {
            $table->dropColumn(['clock_in_latitude', 'clock_in_longitude', 'clock_in_distance_m']);
        });

        Schema::table('gyms', function (Blueprint $table) {
            $table->dropColumn('attendance_radius_m');
        });
    }
};
