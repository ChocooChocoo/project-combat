<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gyms', function (Blueprint $table) {
            $table->string('zip_code', 4)->nullable()->after('province');
        });
    }

    public function down(): void
    {
        Schema::table('gyms', fn (Blueprint $table) => $table->dropColumn('zip_code'));
    }
};
