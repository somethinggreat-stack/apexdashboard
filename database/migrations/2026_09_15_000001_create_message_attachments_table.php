<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Files/images attached to a team-chat message. Bytes live on the PRIVATE disk
 * (storage/app/private) and are only ever served through a guarded, org-scoped
 * download route — these can hold client SSN data, so never a public URL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_message_id')->constrained('team_messages')->cascadeOnDelete();
            $table->string('disk_path');        // path on the 'private' disk
            $table->string('original_name');
            $table->string('mime')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->unsignedInteger('width')->nullable();   // images only
            $table->unsignedInteger('height')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_attachments');
    }
};
