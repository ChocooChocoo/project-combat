<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** R-59: payroll needs an employment end date and a worker type (R-23). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_profiles', function (Blueprint $table) {
            $table->date('ended_on')->nullable()->after('started_on');
            $table->string('employment_type', 20)->default('employee')->after('ended_on');
        });
    }

    public function down(): void
    {
        Schema::table('employee_profiles', fn (Blueprint $table) => $table->dropColumn(['ended_on', 'employment_type']));
    }
};
