<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** R-121: SSS, PhilHealth and Pag-IBIG numbers, stored encrypted (long text). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_profiles', function (Blueprint $table) {
            $table->text('sss_number')->nullable();
            $table->text('philhealth_number')->nullable();
            $table->text('pagibig_number')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('employee_profiles', function (Blueprint $table) {
            $table->dropColumn(['sss_number', 'philhealth_number', 'pagibig_number']);
        });
    }
};
