<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Support for JARVIS write endpoints: an idempotency store, and the account the
 * assistant's writes are attributed to.
 *
 * The account exists because advancing a round credits a VA. `EndUser::booted()`
 * stamps `round_selections.admin_id` from the authenticated admin, and that row is
 * the only signal the Daily Task and Tasks View reports trust. A write with no
 * authenticated admin would credit nobody and quietly under-report whoever earned
 * it. Umair chose a dedicated account (2026-09-22) so assistant work is visibly
 * separate from human work rather than blended into his own numbers.
 *
 * Three things about that account, each deliberate:
 *
 *  - `role = 'system'`, NOT 'super'. The column defaults to 'super', and this row
 *    is created by code rather than by a human filling a form, so it is exactly the
 *    case that would inherit the default silently — which is the bug that made the
 *    read API return 0/0/0/0 with a healthy 200.
 *  - An unusable password. `admins` has no enabled flag, so the only way to make an
 *    account unauthenticatable is a hash nothing can ever match. It owns rows; it
 *    is not an identity anyone can log in as.
 *  - `parent_admin_id` = the owner, so its writes scope to the right org — but
 *    Team Chat's teammate list now excludes 'system', or it would appear in every
 *    VA's sidebar as a person.
 */
return new class extends Migration
{
    public function up(): void
    {
        // One row per JARVIS mutation, keyed by the caller's Idempotency-Key. A
        // retried "advance round" returns the first result instead of advancing twice.
        Schema::create('jarvis_requests', function (Blueprint $table) {
            $table->id();
            $table->string('idempotency_key', 128)->unique();
            $table->string('endpoint', 120);
            $table->string('request_id', 64)->nullable();
            $table->json('params')->nullable();
            $table->json('response')->nullable();
            $table->unsignedSmallInteger('status')->default(200);
            $table->timestamps();

            $table->index('created_at');
        });

        if (! Schema::hasTable('admins')) {
            return;
        }

        $owner = DB::table('admins')->where('role', 'super')->orderBy('id')->value('id');

        DB::table('admins')->updateOrInsert(
            ['email' => 'jarvis@apexgrowthsolution.local'],
            [
                'full_name'       => 'JARVIS (assistant)',
                // Not a bcrypt hash of anything — no password can ever match it.
                'password'        => 'no-login-' . bin2hex(random_bytes(24)),
                'role'            => 'system',
                'parent_admin_id' => $owner,
                'created_at'      => now(),
                'updated_at'      => now(),
            ]
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('jarvis_requests');
        DB::table('admins')->where('email', 'jarvis@apexgrowthsolution.local')->delete();
    }
};
