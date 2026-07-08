<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('end_users', function (Blueprint $table) {
            if (!Schema::hasColumn('end_users', 'credit_monitoring_security_question')) {
                $table->string('credit_monitoring_security_question', 255)->nullable()->after('credit_monitoring_security_answer');
            }
            if (!Schema::hasColumn('end_users', 'credit_monitoring_pin')) {
                $table->text('credit_monitoring_pin')->nullable()->after('credit_monitoring_security_question');
            }
        });

        Schema::table('clients', function (Blueprint $table) {
            if (!Schema::hasColumn('clients', 'intake_security_extra')) {
                // When true, this BO's intake form asks for Security Question +
                // Security Answer + 4-digit PIN instead of a single answer field.
                $table->boolean('intake_security_extra')->default(false)->after('intake_external_url');
            }
        });
    }

    public function down(): void
    {
        Schema::table('end_users', function (Blueprint $table) {
            $table->dropColumn(['credit_monitoring_security_question', 'credit_monitoring_pin']);
        });
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('intake_security_extra');
        });
    }
};
