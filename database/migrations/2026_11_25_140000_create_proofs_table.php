<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proofs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('version');
            $table->string('token', 40)->unique();
            $table->string('image_path');
            $table->string('status')->default('sent'); // sent | approved | changes_requested | superseded
            $table->boolean('replaced')->default(false); // true when the admin uploaded the image
            $table->text('comment')->nullable();         // the customer's change request
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();
            $table->unique(['order_id', 'version']);
        });

        // Keep the editable state so a proof revision can reopen the design.
        Schema::table('order_areas', function (Blueprint $table) {
            $table->longText('state')->nullable()->after('personalisation');
        });

        // A design opened to revise an order that is awaiting proof approval.
        Schema::table('designs', function (Blueprint $table) {
            $table->foreignId('revises_order_line_id')->nullable()->after('group_key')->constrained('order_lines')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('designs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('revises_order_line_id');
        });

        Schema::table('order_areas', function (Blueprint $table) {
            $table->dropColumn('state');
        });

        Schema::dropIfExists('proofs');
    }
};
