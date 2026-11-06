<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('sku', 60)->nullable();
            $table->string('category', 30);
            $table->string('unit', 20);
            $table->string('location', 120);
            $table->integer('quantity')->default(0);
            $table->unsignedInteger('min_level')->nullable();
            $table->unsignedInteger('reorder_qty')->nullable();
            $table->decimal('unit_cost', 12, 2)->nullable();
            $table->decimal('sale_price', 12, 2)->nullable();
            $table->string('status', 20)->default('active');
            $table->dateTime('low_notified_at')->nullable();
            $table->timestamps();
            $table->unique(['gym_id', 'sku']);
            $table->index(['gym_id', 'category']);
            $table->index(['gym_id', 'status']);
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stock_item_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            $table->integer('quantity');
            $table->integer('balance_after');
            $table->string('note', 255)->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('occurred_at');
            $table->foreignId('issued_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('returns_movement_id')->nullable()->constrained('stock_movements')->cascadeOnDelete();
            $table->unsignedBigInteger('goods_receipt_line_id')->nullable()->index();
            $table->unsignedBigInteger('payment_id')->nullable()->index();
            $table->timestamps();
            $table->index(['stock_item_id', 'id']);
            $table->index(['gym_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('stock_items');
    }
};
