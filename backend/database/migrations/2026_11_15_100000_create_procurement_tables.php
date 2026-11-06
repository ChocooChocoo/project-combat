<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * R-75 to R-79, R-66: activity needs, approvals, suppliers, orders, deliveries
 * and the append-only supplier payments that settle the derived payable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('contact_person', 120)->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('address', 255)->nullable();
            $table->string('payment_terms', 120)->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->index(['gym_id', 'status']);
        });

        Schema::create('supply_needs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stock_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('training_session_id')->nullable()->constrained()->nullOnDelete();
            $table->date('needed_by')->nullable();
            $table->unsignedInteger('quantity');
            $table->string('note', 255)->nullable();
            $table->string('status', 20)->default('open');
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->index(['gym_id', 'status', 'needed_by']);
            $table->index(['gym_id', 'stock_item_id']);
        });

        Schema::create('purchase_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->string('number', 30)->unique();
            $table->string('status', 20)->default('pending');
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('decided_at')->nullable();
            $table->text('decision_reason')->nullable();
            $table->date('needed_by')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->index(['gym_id', 'status']);
        });

        Schema::create('purchase_request_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stock_item_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity');
            $table->decimal('est_unit_cost', 12, 2)->nullable();
            $table->foreignId('supply_need_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->index(['purchase_request_id', 'id']);
        });

        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->string('number', 30)->unique();
            $table->foreignId('supplier_id')->constrained();
            $table->foreignId('purchase_request_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->boolean('is_direct')->default(false);
            $table->string('status', 20)->default('pending');
            $table->foreignId('ordered_by')->constrained('users')->cascadeOnDelete();
            $table->date('expected_on')->nullable();
            $table->decimal('total', 12, 2);
            $table->boolean('closed_short')->default(false);
            $table->text('cancel_reason')->nullable();
            $table->timestamps();
            $table->index(['gym_id', 'status']);
            $table->index(['gym_id', 'supplier_id']);
        });

        Schema::create('purchase_order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stock_item_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity_ordered');
            $table->unsignedInteger('quantity_received')->default(0);
            $table->decimal('unit_cost', 12, 2);
            $table->timestamps();
            $table->index(['purchase_order_id', 'id']);
        });

        Schema::create('goods_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->string('number', 30)->unique();
            $table->foreignId('purchase_order_id')->constrained();
            $table->foreignId('received_by')->constrained('users')->cascadeOnDelete();
            $table->date('received_on');
            $table->string('note', 255)->nullable();
            $table->decimal('total', 12, 2);
            $table->timestamps();
            $table->index(['gym_id', 'received_on']);
            $table->index(['purchase_order_id', 'id']);
        });

        Schema::create('goods_receipt_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goods_receipt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_order_line_id')->constrained();
            $table->unsignedInteger('quantity');
            $table->decimal('unit_cost', 12, 2);
            $table->decimal('amount', 12, 2);
            $table->timestamps();
            $table->index(['goods_receipt_id', 'id']);
        });

        Schema::create('supplier_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained();
            $table->foreignId('purchase_order_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('method', 20);
            $table->date('paid_on');
            $table->string('reference', 80)->nullable();
            $table->string('note', 255)->nullable();
            $table->foreignId('corrects_id')->nullable()->constrained('supplier_payments')->nullOnDelete();
            $table->foreignId('recorded_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->index(['gym_id', 'supplier_id', 'paid_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_payments');
        Schema::dropIfExists('goods_receipt_lines');
        Schema::dropIfExists('goods_receipts');
        Schema::dropIfExists('purchase_order_lines');
        Schema::dropIfExists('purchase_orders');
        Schema::dropIfExists('purchase_request_lines');
        Schema::dropIfExists('purchase_requests');
        Schema::dropIfExists('supply_needs');
        Schema::dropIfExists('suppliers');
    }
};
