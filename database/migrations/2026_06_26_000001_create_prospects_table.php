<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Prospective business owners — people who aren't clients yet and are
        // still deciding whether to work with us. A lightweight sales pipeline:
        // name, phone and a running discussion/notes log per status.
        Schema::create('prospects', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('admin_id')->nullable()->index();
            $table->string('name');
            $table->string('phone', 40)->nullable();
            $table->string('status', 30)->default('new')->index();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prospects');
    }
};
