<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('session_booking_id')->nullable()->index()->constrained()->nullOnDelete();
        });
        Schema::table('session_bookings', function (Blueprint $table) {
            $table->string('pending_checkout_id')->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('session_booking_id');
        });
        Schema::table('session_bookings', function (Blueprint $table) {
            $table->dropUnique(['pending_checkout_id']);
            $table->dropColumn('pending_checkout_id');
        });
    }
};
