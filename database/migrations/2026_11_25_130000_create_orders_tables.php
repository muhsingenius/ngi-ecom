<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_zones', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('fee_type')->default('flat'); // flat | banded
            $table->decimal('flat_fee', 10, 2)->default(0);
            $table->json('bands')->nullable();           // [{min_qty, max_qty|null, fee}]
            $table->string('eta')->nullable();           // e.g. "1-2 days"
            $table->string('courier_name')->nullable();
            $table->decimal('free_threshold', 10, 2)->nullable();
            $table->boolean('active')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('pickup_points', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('address');
            $table->string('hours')->nullable();
            $table->boolean('active')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('number')->nullable()->unique();
            $table->string('access_token', 40)->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->string('customer_name');
            $table->string('phone', 30);
            $table->string('email');

            $table->string('status')->default('pending_payment')->index();
            $table->string('production_stage')->nullable(); // queued | printing | ready (set by the print shop)

            $table->string('fulfilment');                   // delivery | pickup
            $table->foreignId('delivery_zone_id')->nullable()->constrained()->nullOnDelete();
            $table->string('zone_name')->nullable();
            $table->string('zone_eta')->nullable();
            $table->foreignId('pickup_point_id')->nullable()->constrained()->nullOnDelete();
            $table->string('pickup_name')->nullable();
            $table->text('pickup_address')->nullable();
            $table->text('delivery_address')->nullable();

            $table->string('currency', 3)->default('GHS');
            $table->decimal('subtotal', 10, 2);
            $table->decimal('delivery_fee', 10, 2)->default(0);
            $table->decimal('total', 10, 2);
            $table->unsignedInteger('quantity');
            $table->string('tier_scope');

            $table->boolean('proof_required')->default(false);
            $table->string('proof_timing')->nullable();     // pay_then_proof | proof_then_pay
            $table->timestamp('proof_expires_at')->nullable();
            $table->unsignedSmallInteger('revisions_used')->default(0);
            $table->boolean('flagged')->default(false);

            $table->string('paystack_reference')->nullable()->unique();
            $table->timestamp('paid_at')->nullable();
            $table->date('due_date')->nullable();

            $table->string('courier')->nullable();
            $table->string('rider_phone', 30)->nullable();
            $table->text('tracking_note')->nullable();
            $table->text('notes')->nullable();

            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('design_id')->nullable()->constrained()->nullOnDelete();
            $table->string('product_name');
            $table->string('quality');
            $table->string('product_type');
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 10, 2);       // tier price, before size surcharge and extra areas
            $table->decimal('single_price', 10, 2);
            $table->decimal('extra_area_fee', 10, 2)->default(0);
            $table->decimal('saving', 10, 2)->default(0);
            $table->decimal('line_total', 10, 2);
            $table->unsignedInteger('tier_quantity');
            $table->string('design_group_key', 36)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_line_id')->constrained()->cascadeOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->string('colour_name');
            $table->string('colour_hex', 7);
            $table->string('size');
            $table->unsignedInteger('qty');
            $table->decimal('unit_price', 10, 2);       // what one of these costs, all in
            $table->string('name')->nullable();
            $table->string('number', 10)->nullable();
        });

        // Immutable copies of the print files taken when the order is placed, so later edits to a design never change what is printed.
        Schema::create('order_areas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_line_id')->constrained()->cascadeOnDelete();
            $table->string('area_name');
            $table->string('method');
            $table->decimal('width_cm', 6, 2);
            $table->decimal('height_cm', 6, 2);
            $table->string('export_path');
            $table->string('preview_path')->nullable();
            $table->string('mockup_path')->nullable();
            $table->decimal('box_x', 5, 2);
            $table->decimal('box_y', 5, 2);
            $table->decimal('box_w', 5, 2);
            $table->decimal('box_h', 5, 2);
            $table->unsignedInteger('export_width');
            $table->unsignedInteger('export_height');
            $table->unsignedSmallInteger('export_dpi');
            $table->json('personalisation')->nullable();
            $table->json('assets')->nullable();          // original uploads kept alongside: [{name, path}]
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('reference')->unique();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3);
            $table->string('channel')->nullable();
            $table->string('status');
            $table->json('payload')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        Schema::create('order_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 40);
            $table->text('message');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        foreach (['order_events', 'payments', 'order_areas', 'order_items', 'order_lines', 'orders', 'pickup_points', 'delivery_zones'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
