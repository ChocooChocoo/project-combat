<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * R-63: every payment carries the income type it belongs to, and a walk-in sale
 * may have no account attached.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', fn (Blueprint $table) => $table->dropForeign(['user_id']));

        Schema::table('payments', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->change();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->string('income_type', 20)->default('other')->after('method');
            $table->decimal('discount', 12, 2)->default(0)->after('amount');
            $table->string('payer_name', 150)->nullable()->after('user_id');
            $table->index(['gym_id', 'paid_at']);
            $table->index(['gym_id', 'income_type', 'paid_at']);
        });

        DB::table('payments')->whereNotNull('membership_id')->update(['income_type' => 'membership']);
        DB::table('payments')->whereNotNull('session_booking_id')->update(['income_type' => 'session']);
        DB::table('payments')->whereNotNull('session_booking_id')
            ->whereIn('session_booking_id', function ($query) {
                $query->select('session_bookings.id')
                    ->from('session_bookings')
                    ->join('training_sessions', 'training_sessions.id', '=', 'session_bookings.training_session_id')
                    ->where('training_sessions.type', 'private_coaching');
            })
            ->update(['income_type' => 'private_coaching']);
    }

    public function down(): void
    {
        if (DB::table('payments')->whereNull('user_id')->exists()) {
            throw new RuntimeException('Walk-in payments have no account and cannot be represented after rollback. Restore a backup instead.');
        }

        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['gym_id', 'paid_at']);
            $table->dropIndex(['gym_id', 'income_type', 'paid_at']);
            $table->dropColumn(['income_type', 'discount', 'payer_name']);
            $table->dropForeign(['user_id']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};
