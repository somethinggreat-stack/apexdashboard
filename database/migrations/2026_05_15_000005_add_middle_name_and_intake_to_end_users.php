<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('end_users', function (Blueprint $table) {
            $table->string('middle_name', 100)->nullable()->after('first_name');
            $table->string('intake_status', 32)->nullable()->after('status'); // 'pending_review' | 'approved' | null
            $table->string('intake_submitted_ip', 45)->nullable();
            $table->timestamp('intake_submitted_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('end_users', function (Blueprint $table) {
            $table->dropColumn(['middle_name', 'intake_status', 'intake_submitted_ip', 'intake_submitted_at']);
        });
    }
};
