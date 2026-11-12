<?php

use App\Support\PermissionCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** R-111: promos (automatic) and vouchers (code) on membership plans, approved before they work. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 20);
            $table->string('name', 120);
            $table->string('code', 40)->nullable();
            $table->string('discount_type', 20);
            $table->decimal('value', 12, 2);
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->unsignedInteger('max_uses')->nullable();
            $table->unsignedInteger('uses')->default(0);
            $table->string('status', 20)->default('pending');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('decided_at')->nullable();
            $table->text('decision_reason')->nullable();
            $table->timestamps();
            $table->unique(['gym_id', 'code']);
            $table->index(['gym_id', 'status']);
        });

        Schema::create('offer_plan', function (Blueprint $table) {
            $table->foreignId('offer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('membership_plan_id')->constrained()->cascadeOnDelete();
            $table->primary(['offer_id', 'membership_plan_id']);
        });

        Schema::table('memberships', function (Blueprint $table) {
            $table->string('voucher_code', 40)->nullable()->after('prepaid_terms');
            $table->foreignId('offer_id')->nullable()->after('voucher_code')->constrained()->nullOnDelete();
            $table->decimal('discount_amount', 12, 2)->default(0)->after('offer_id');
        });

        PermissionCatalog::sync();
    }

    public function down(): void
    {
        Schema::table('memberships', function (Blueprint $table) {
            $table->dropConstrainedForeignId('offer_id');
            $table->dropColumn(['voucher_code', 'discount_amount']);
        });
        Schema::dropIfExists('offer_plan');
        Schema::dropIfExists('offers');
    }
};
