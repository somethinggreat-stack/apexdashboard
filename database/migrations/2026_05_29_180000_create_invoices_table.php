<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->onDelete('cascade');
            $table->string('invoice_number', 50)->unique();
            $table->date('invoice_date');
            $table->json('items');                 // [{end_user_id, name, round, amount}]
            $table->decimal('total', 10, 2);
            $table->foreignId('created_by_admin_id')->constrained('admins');
            $table->timestamps();

            $table->index('invoice_date');
            $table->index(['client_id', 'invoice_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
