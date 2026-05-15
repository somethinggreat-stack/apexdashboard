<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('end_users', function (Blueprint $table) {
            $table->string('ssn_picture_path')->nullable()->after('ssn');
            $table->string('cfpb_email')->nullable()->after('credit_monitoring_password');
            $table->text('cfpb_password')->nullable()->after('cfpb_email');
        });
    }

    public function down(): void
    {
        Schema::table('end_users', function (Blueprint $table) {
            $table->dropColumn(['ssn_picture_path', 'cfpb_email', 'cfpb_password']);
        });
    }
};
