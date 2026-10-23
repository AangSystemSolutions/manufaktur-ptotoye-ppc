<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Menu 1: Sales Orders & BOM
        Schema::create('sales_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_no')->unique();
            $table->string('customer_name');
            $table->string('product_name');
            $table->integer('quantity');
            $table->text('drawing_spec')->nullable();
            $table->enum('status', ['Draft', 'Ready to Schedule', 'Scheduled', 'In Progress', 'QC Passed', 'Completed'])->default('Draft');
            $table->timestamps();
        });

        Schema::create('bill_of_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_order_id')->constrained('sales_orders')->onDelete('cascade');
            $table->string('material_code');
            $table->string('material_name');
            $table->decimal('required_qty', 10, 2);
            $table->string('unit');
            $table->timestamps();
        });

        // 2. Menu 2: Inventory & MRP
        Schema::create('inventories', function (Blueprint $table) {
            $table->id();
            $table->string('item_code')->unique();
            $table->string('item_name');
            $table->decimal('stock_qty', 10, 2)->default(0);
            $table->decimal('allocated_qty', 10, 2)->default(0);
            $table->string('unit');
            $table->enum('item_type', ['Raw Material', 'Finished Good']);
            $table->timestamps();
        });

        Schema::create('purchase_requisitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_order_id')->constrained('sales_orders')->onDelete('cascade');
            $table->string('material_code');
            $table->decimal('shortage_qty', 10, 2);
            $table->enum('status', ['Pending', 'Ordered', 'Received'])->default('Pending');
            $table->timestamps();
        });

        // 3. Menu 3: Production Orders (SPK) & Scheduling
        Schema::create('production_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_order_id')->constrained('sales_orders')->onDelete('cascade');
            $table->string('spk_no')->unique();
            $table->string('machine_target')->nullable();
            $table->dateTime('scheduled_start')->nullable();
            $table->dateTime('scheduled_end')->nullable();
            $table->enum('status', ['Draft', 'Ready to Schedule', 'Scheduled', 'In Progress', 'Completed'])->default('Draft');
            $table->timestamps();
        });

        // 4. Menu 4: Shop Floor Tracking
        Schema::create('shop_floor_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_order_id')->constrained('production_orders')->onDelete('cascade');
            $table->string('operator_id');
            $table->enum('scan_type', ['START', 'STOP']);
            $table->integer('qty_ok')->default(0);
            $table->integer('qty_scrap')->default(0);
            $table->integer('downtime_minutes')->default(0);
            $table->timestamp('scan_time')->useCurrent();
            $table->timestamps();
        });

        // 5. Menu 5: Quality Control & Delivery
        Schema::create('quality_controls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_order_id')->constrained('production_orders')->onDelete('cascade');
            $table->integer('inspected_qty');
            $table->integer('passed_qty');
            $table->integer('rejected_qty');
            $table->enum('qc_status', ['Passed', 'Rejected']);
            $table->timestamps();
        });

        Schema::create('delivery_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_order_id')->constrained('sales_orders')->onDelete('cascade');
            $table->string('do_no')->unique();
            $table->date('delivery_date');
            $table->enum('status', ['Prepared', 'Delivered'])->default('Prepared');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_orders');
        Schema::dropIfExists('quality_controls');
        Schema::dropIfExists('shop_floor_logs');
        Schema::dropIfExists('production_orders');
        Schema::dropIfExists('purchase_requisitions');
        Schema::dropIfExists('inventories');
        Schema::dropIfExists('bill_of_materials');
        Schema::dropIfExists('sales_orders');
    }
};