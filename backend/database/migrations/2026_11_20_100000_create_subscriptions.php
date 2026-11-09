<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// R-102 to R-107: platform subscription plans, the free trial and each gym's paid period.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60)->unique();
            $table->decimal('monthly_price', 10, 2)->default(0);
            $table->text('inclusions')->nullable();
            $table->json('modules');
            $table->boolean('is_free')->default(false);
            $table->timestamp('retired_at')->nullable();
            $table->timestamps();
        });

        Schema::create('platform_settings', function (Blueprint $table) {
            $table->string('key', 60)->primary();
            $table->json('value');
            $table->timestamps();
        });

        Schema::table('gyms', function (Blueprint $table) {
            $table->timestamp('trial_ends_at')->nullable();
            $table->foreignId('subscription_plan_id')->nullable()->constrained('subscription_plans')->nullOnDelete();
            $table->timestamp('paid_until')->nullable();
            $table->string('pending_subscription_checkout_id')->nullable()->unique();
            $table->foreignId('pending_subscription_plan_id')->nullable()->constrained('subscription_plans')->nullOnDelete();
            $table->timestamp('subscription_reminded_for')->nullable();
        });

        Schema::create('subscription_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_plan_id')->constrained('subscription_plans');
            $table->string('plan_name', 60);
            $table->decimal('amount', 10, 2);
            $table->string('paymongo_checkout_id')->unique();
            $table->string('paymongo_payment_id')->nullable();
            $table->timestamp('period_starts_at');
            $table->timestamp('period_ends_at');
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        $now = now();
        $everything = ['hr', 'training', 'members', 'health_records', 'finance', 'payroll', 'customers', 'inventory', 'procurement', 'announcements', 'decision_support'];
        DB::table('subscription_plans')->insert([
            ['name' => 'Starter', 'monthly_price' => 0, 'is_free' => true, 'created_at' => $now, 'updated_at' => $now,
                'inclusions' => "Classes, bookings and check-in\nMembers and membership plans\nAnnouncements",
                'modules' => json_encode(['training', 'members', 'health_records', 'announcements'])],
            ['name' => 'Pro', 'monthly_price' => 1499, 'is_free' => false, 'created_at' => $now, 'updated_at' => $now,
                'inclusions' => "Everything in Starter\nStaff, attendance and payroll\nFinance and customers\nStock",
                'modules' => json_encode(['hr', 'training', 'members', 'health_records', 'finance', 'payroll', 'customers', 'inventory', 'announcements'])],
            ['name' => 'Elite', 'monthly_price' => 2999, 'is_free' => false, 'created_at' => $now, 'updated_at' => $now,
                'inclusions' => "Everything in Pro\nPurchasing\nDecision support",
                'modules' => json_encode($everything)],
        ]);
        DB::table('platform_settings')->insert(['key' => 'trial_days', 'value' => json_encode(30), 'created_at' => $now, 'updated_at' => $now]);

        // Gyms approved before subscriptions existed start their trial today.
        DB::table('gyms')->where('status', 'approved')->update(['trial_ends_at' => $now->copy()->addDays(30)]);
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_payments');
        Schema::table('gyms', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pending_subscription_plan_id');
            $table->dropConstrainedForeignId('subscription_plan_id');
            $table->dropUnique(['pending_subscription_checkout_id']);
            $table->dropColumn(['trial_ends_at', 'paid_until', 'pending_subscription_checkout_id', 'subscription_reminded_for']);
        });
        Schema::dropIfExists('platform_settings');
        Schema::dropIfExists('subscription_plans');
    }
};
