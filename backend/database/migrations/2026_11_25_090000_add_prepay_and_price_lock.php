<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** R-109 and R-110: plans can be prepaid for several terms; a membership keeps the fee it was approved at. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('membership_plans', function (Blueprint $table) {
            $table->unsignedTinyInteger('max_prepay_terms')->default(1)->after('total_fee');
            $table->decimal('prepay_discount_percent', 5, 2)->default(0)->after('max_prepay_terms');
        });

        Schema::table('memberships', function (Blueprint $table) {
            $table->decimal('locked_fee', 12, 2)->nullable()->after('amount_charged');
            $table->unsignedTinyInteger('prepaid_terms')->default(1)->after('locked_fee');
        });

        // Live memberships lock today's plan fee so a later price change never reaches them.
        DB::table('memberships')
            ->whereIn('status', ['awaiting_payment', 'active', 'frozen'])
            ->update(['locked_fee' => DB::raw('(select total_fee from membership_plans where membership_plans.id = memberships.membership_plan_id)')]);
    }

    public function down(): void
    {
        Schema::table('memberships', function (Blueprint $table) {
            $table->dropColumn(['locked_fee', 'prepaid_terms']);
        });

        Schema::table('membership_plans', function (Blueprint $table) {
            $table->dropColumn(['max_prepay_terms', 'prepay_discount_percent']);
        });
    }
};
