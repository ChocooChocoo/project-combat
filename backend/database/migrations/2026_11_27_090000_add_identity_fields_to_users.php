<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// R-128, R-129, R-130: full name, birthdate, guardian and home area.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('middle_name', 100)->nullable()->after('first_name');
            $table->string('suffix', 10)->nullable()->after('last_name');
            $table->date('birthdate')->nullable();
            $table->string('guardian_name', 150)->nullable();
            $table->string('guardian_phone', 30)->nullable();
            $table->string('guardian_relationship', 60)->nullable();
            $table->dateTime('guardian_consent_at')->nullable();
            $table->string('city', 120)->nullable();
            $table->string('province', 120)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['middle_name', 'suffix', 'birthdate', 'guardian_name', 'guardian_phone',
                'guardian_relationship', 'guardian_consent_at', 'city', 'province']);
        });
    }
};
