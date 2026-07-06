<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('extra_projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->constrained('admins')->onDelete('cascade');
            $table->string('type', 20)->index();          // funnel | support | ads
            $table->string('client_name');
            $table->string('link', 1000)->nullable();       // funnel link
            $table->string('whatsapp', 40)->nullable();     // support contact number
            $table->decimal('amount', 10, 2)->nullable();   // agreed price: one-time / per-week / weekly
            $table->decimal('paid', 10, 2)->nullable();     // upfront / total received so far
            $table->string('status', 30)->default('in_progress');
            $table->text('notes')->nullable();
            $table->foreignId('created_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();

            $table->index(['admin_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('extra_projects');
    }
};
