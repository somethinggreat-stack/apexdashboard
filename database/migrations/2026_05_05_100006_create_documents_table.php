<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('end_user_id')->constrained('end_users')->onDelete('cascade');
            $table->foreignId('process_step_id')->nullable()->constrained('process_steps')->onDelete('cascade');
            $table->string('file_name');
            $table->enum('file_type', ['pdf', 'image', 'audio', 'other']);
            $table->string('file_path');
            $table->enum('category', [
                'credit_report',
                'dispute_letter_experian',
                'dispute_letter_equifax',
                'dispute_letter_transunion',
                'dispute_letter_innovis',
                'cfpb_complaint_experian',
                'cfpb_complaint_equifax',
                'cfpb_complaint_transunion',
                'cfpb_complaint_innovis',
                'ftc_complaint',
                'bureau_response',
                'escalation_letter',
                'call_recording',
                'call_notes',
                'tracking_receipt',
                'other',
            ]);
            $table->string('description')->nullable();
            $table->timestamps();

            $table->index('end_user_id');
            $table->index('process_step_id');
            $table->index('category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
