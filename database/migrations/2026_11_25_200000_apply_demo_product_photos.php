<?php

use App\Support\DemoPhotos;
use Illuminate\Database\Migrations\Migration;

/**
 * Stores that were seeded before the product photos existed get them now (a no-op on an empty catalogue).
 */
return new class extends Migration
{
    public function up(): void
    {
        DemoPhotos::apply();
    }

    public function down(): void
    {
        //
    }
};
