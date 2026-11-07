<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('session_bookings', function (Blueprint $table) {
            $table->timestamp('reminder_sent_at')->nullable();
        });

        Schema::create('membership_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('membership_id')->constrained()->cascadeOnDelete();
            $table->date('ends_on');
            $table->unsignedSmallInteger('days_before');
            $table->timestamps();
            $table->unique(['membership_id', 'ends_on', 'days_before']);
            $table->index(['membership_id', 'ends_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('membership_reminders');
        Schema::table('session_bookings', function (Blueprint $table) {
            $table->dropColumn('reminder_sent_at');
        });
    }
};
