<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** R-56, R-59, R-61: the gym's attendance and payroll settings. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gyms', function (Blueprint $table) {
            $table->string('payroll_frequency', 20)->default('semi_monthly');
            $table->json('payroll_steps')->nullable();
            $table->unsignedSmallInteger('staff_grace_minutes')->default(10);
            $table->unsignedSmallInteger('overtime_min_minutes')->default(30);
        });
    }

    public function down(): void
    {
        Schema::table('gyms', fn (Blueprint $table) => $table->dropColumn([
            'payroll_frequency', 'payroll_steps', 'staff_grace_minutes', 'overtime_min_minutes',
        ]));
    }
};
