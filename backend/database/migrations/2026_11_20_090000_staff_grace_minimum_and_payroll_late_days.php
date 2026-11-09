<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// R-99: staff grace is never below 10 minutes. R-100: payroll lines keep a count of late days.
return new class extends Migration
{
    public function up(): void
    {
        DB::table('gyms')->where('staff_grace_minutes', '<', 10)->update(['staff_grace_minutes' => 10]);

        Schema::table('payroll_lines', function (Blueprint $table) {
            $table->unsignedSmallInteger('days_late')->default(0)->after('days_worked');
        });
    }

    public function down(): void
    {
        Schema::table('payroll_lines', function (Blueprint $table) {
            $table->dropColumn('days_late');
        });
    }
};
