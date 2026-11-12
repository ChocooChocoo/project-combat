<?php

use App\Support\PermissionCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** R-119 injuries with a recovery log; R-120 safety checks on equipment and protective gear. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('injuries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('body_part', 100);
            $table->text('description')->nullable();
            $table->string('severity', 20);
            $table->date('occurred_on');
            $table->string('status', 20)->default('active');
            $table->text('restrictions')->nullable();
            $table->date('cleared_on')->nullable();
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['gym_id', 'user_id', 'status']);
        });

        Schema::create('injury_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->foreignId('injury_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20);
            $table->text('note')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('stock_items', function (Blueprint $table) {
            $table->string('safety_status', 20)->default('ok');
            $table->unsignedSmallInteger('check_interval_days')->nullable();
            $table->date('next_check_on')->nullable();
            // The next_check_on value already reminded about, so each due date is told once.
            $table->date('check_reminded_for')->nullable();
        });

        Schema::create('equipment_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stock_item_id')->constrained()->cascadeOnDelete();
            $table->string('result', 20);
            $table->text('note')->nullable();
            $table->foreignId('checked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('checked_on');
            $table->timestamps();
        });

        PermissionCatalog::sync();
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment_checks');
        Schema::table('stock_items', function (Blueprint $table) {
            $table->dropColumn(['safety_status', 'check_interval_days', 'next_check_on', 'check_reminded_for']);
        });
        Schema::dropIfExists('injury_updates');
        Schema::dropIfExists('injuries');
    }
};
