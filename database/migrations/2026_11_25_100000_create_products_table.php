<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('quality')->default('Standard');
            $table->text('description')->nullable();
            $table->string('image')->nullable();
            $table->unsignedTinyInteger('included_print_areas')->default(1);
            $table->string('tier_scope_override')->nullable();
            $table->boolean('active')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('product_price_tiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('min_qty');
            $table->decimal('unit_price', 10, 2);
            $table->unique(['product_id', 'min_qty']);
        });

        Schema::create('product_colours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('hex', 7);
            $table->boolean('active')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_colour_id')->constrained()->cascadeOnDelete();
            $table->string('size');
            $table->decimal('size_surcharge', 10, 2)->default(0);
            $table->boolean('active')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
            $table->unique(['product_colour_id', 'size']);
        });

        Schema::create('print_areas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->decimal('width_cm', 6, 2);
            $table->decimal('height_cm', 6, 2);
            $table->string('method')->default('dtf');
            $table->unsignedTinyInteger('max_colours')->nullable();
            $table->decimal('min_line_mm', 5, 1)->nullable();
            $table->unsignedSmallInteger('max_chars')->nullable();
            $table->decimal('extra_fee', 10, 2)->default(0);
            $table->boolean('allows_name')->default(false);
            $table->boolean('allows_number')->default(false);
            // Position of the printable box on the mockup image, in percent.
            $table->decimal('box_x', 5, 2)->default(30);
            $table->decimal('box_y', 5, 2)->default(25);
            $table->decimal('box_w', 5, 2)->default(40);
            $table->decimal('box_h', 5, 2)->default(40);
            $table->boolean('active')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
            $table->unique(['product_id', 'slug']);
        });

        Schema::create('mockups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_colour_id')->constrained()->cascadeOnDelete();
            $table->foreignId('print_area_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->timestamps();
            $table->unique(['product_colour_id', 'print_area_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mockups');
        Schema::dropIfExists('print_areas');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('product_colours');
        Schema::dropIfExists('product_price_tiers');
        Schema::dropIfExists('products');
    }
};
