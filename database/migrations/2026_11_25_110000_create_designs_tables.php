<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('designs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('session_token', 64)->nullable()->index();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_colour_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name')->nullable();
            // Designs copied to another product keep the same group key (used for "per design" tiers).
            $table->string('group_key', 36)->index();
            $table->timestamps();
        });

        Schema::create('design_areas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('design_id')->constrained()->cascadeOnDelete();
            $table->foreignId('print_area_id')->constrained()->cascadeOnDelete();
            $table->longText('state');                    // Fabric.js JSON, for re-editing
            $table->json('personalisation')->nullable();  // name/number placeholder layout (cm, relative to the area)
            $table->string('export_path')->nullable();    // print-ready PNG (private disk)
            $table->string('preview_path')->nullable();   // small PNG for mockups and proofs
            $table->unsignedInteger('export_width')->nullable();
            $table->unsignedInteger('export_height')->nullable();
            $table->unsignedSmallInteger('export_dpi')->nullable();
            $table->unsignedTinyInteger('colours_count')->nullable();
            $table->timestamps();
            $table->unique(['design_id', 'print_area_id']);
        });

        Schema::create('design_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('session_token', 64)->nullable()->index();
            $table->string('path');
            $table->string('original_name')->nullable();
            $table->string('mime', 40);
            $table->string('kind', 10); // raster | svg
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->unsignedInteger('bytes');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('design_assets');
        Schema::dropIfExists('design_areas');
        Schema::dropIfExists('designs');
    }
};
