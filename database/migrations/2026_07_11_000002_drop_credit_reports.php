<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Removes the Letter Generator tables. The feature was dropped; its create
 * migration was deleted, so this cleans the tables off any environment where
 * that migration had already run (production). dropIfExists is safe on a fresh
 * install where the tables never existed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('credit_report_items');
        Schema::dropIfExists('credit_reports');
    }

    public function down(): void
    {
        // Intentionally empty — the feature is gone and not coming back here.
    }
};
