<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The mailboxes this dashboard created on cPanel for the CFPB workflow.
 *
 * A row is the dashboard's record of a real mailbox; cPanel remains the source
 * of truth for whether it exists. Deleting one keeps the row (deleted_at set)
 * so the audit trail survives — who created which address, for which client,
 * and when it was taken away again.
 *
 * The password is stored because the VA has to type it into webmail. It is
 * encrypted at rest with the same cast ssn and cfpb_password already use, so
 * a database dump alone reveals nothing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mailboxes', function (Blueprint $table) {
            $table->id();

            // Which organisation's mailbox this is (admins.dataOwnerId()).
            $table->unsignedBigInteger('admin_id')->index();
            // The VA who created it, kept even if their account is later removed.
            $table->unsignedBigInteger('created_by_admin_id')->nullable()->index();
            // The client it was made for. Optional: a VA may create one ahead of time.
            $table->unsignedBigInteger('end_user_id')->nullable()->index();

            $table->string('local_part', 64);
            $table->string('domain', 191);
            $table->string('address', 255)->unique();   // local_part@domain, the natural key
            $table->text('password');                   // encrypted cast
            $table->unsignedInteger('quota_mb')->default(100);

            $table->timestamp('deleted_at')->nullable()->index();
            $table->unsignedBigInteger('deleted_by_admin_id')->nullable();

            $table->timestamps();

            // The Mailboxes page lists one org's live mailboxes, newest first.
            $table->index(['admin_id', 'deleted_at']);
            // The per-VA daily cap counts today's rows for one creator.
            $table->index(['created_by_admin_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mailboxes');
    }
};
