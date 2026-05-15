<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('end_user_id')->constrained('end_users')->onDelete('cascade');
            $table->text('note_text');
            $table->foreignId('created_by_admin_id')->constrained('admins');
            $table->timestamps();

            $table->index('end_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notes');
    }
};
