<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Lightweight prospect leads — a simple list of leads with a verified
        // WhatsApp number and optional Instagram / website links. Separate from
        // the richer "prospects" pipeline.
        Schema::create('prospect_leads', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('admin_id')->nullable()->index();
            $table->string('name');
            $table->string('whatsapp', 40)->nullable();
            $table->string('instagram')->nullable();
            $table->string('website')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prospect_leads');
    }
};
