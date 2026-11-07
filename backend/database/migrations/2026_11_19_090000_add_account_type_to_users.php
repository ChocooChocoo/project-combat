<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('account_type', 20)->default('member');
        });

        // Existing accounts: owning a gym or having any gym application means owner.
        DB::table('users')
            ->whereExists(fn ($query) => $query->select(DB::raw(1))
                ->from('gyms')
                ->whereColumn('gyms.applicant_id', 'users.id'))
            ->orWhereExists(fn ($query) => $query->select(DB::raw(1))
                ->from('gym_user')
                ->whereColumn('gym_user.user_id', 'users.id')
                ->where('gym_user.role', 'owner'))
            ->update(['account_type' => 'owner']);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('account_type');
        });
    }
};
