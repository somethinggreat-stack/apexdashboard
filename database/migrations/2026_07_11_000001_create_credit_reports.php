<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Letter Generator — stage 1 storage.
 *
 * A `credit_report` is one uploaded MyFreeScoreNow / 3-bureau report that the
 * super admin audits. `credit_report_items` are the individual accounts,
 * inquiries, public records and addresses pulled out of it, each with the audit
 * verdict and (once the admin selects it) the dispute reason / instruction.
 *
 * The raw HTML is NOT stored — it holds live SSNs. We parse it and keep only
 * the structured, non-identifying fields we need.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('uploaded_by_admin_id')->constrained('admins')->cascadeOnDelete();
            $table->string('consumer_name')->nullable();
            $table->date('report_date')->nullable();
            $table->string('source', 40)->default('mfsn');      // which provider format
            $table->unsignedSmallInteger('account_count')->default(0);
            $table->unsignedSmallInteger('inquiry_count')->default(0);
            $table->unsignedSmallInteger('negative_count')->default(0);
            $table->timestamps();

            $table->index('uploaded_by_admin_id');
        });

        Schema::create('credit_report_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credit_report_id')->constrained('credit_reports')->cascadeOnDelete();

            // account | inquiry | public_record | personal_info
            $table->string('item_type', 20);
            // account classification or address type — collection, charge_off,
            // closed_late, open_late, open, closed_positive, bankruptcy, address …
            $table->string('category', 40)->nullable();

            $table->string('creditor_name')->nullable();         // or the address line / inquiry name
            $table->string('account_number')->nullable();        // masked, as reported

            // per-bureau field matrix + payment history + everything parsed
            $table->json('detail')->nullable();
            $table->date('open_date')->nullable();               // earliest open date across bureaus

            $table->boolean('is_negative')->default(false);      // audit verdict
            $table->string('auto_reason')->nullable();           // why the audit flagged it

            // filled once the admin selects the item to dispute
            $table->boolean('selected')->default(false);
            $table->text('dispute_instruction')->nullable();     // accounts + inquiries only
            $table->text('dispute_reason')->nullable();

            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();

            $table->index(['credit_report_id', 'item_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_report_items');
        Schema::dropIfExists('credit_reports');
    }
};
