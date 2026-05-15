<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20)->default('popup')->index();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->index();
            $table->string('phone');
            $table->string('score')->nullable();
            $table->string('goal')->nullable();
            $table->string('urgency')->nullable();
            $table->string('subject')->nullable();
            $table->text('message')->nullable();
            $table->json('details')->nullable();
            $table->string('source_page')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
