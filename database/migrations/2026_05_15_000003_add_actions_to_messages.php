<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->foreignId('reply_to_id')->nullable()->after('body')
                ->constrained('messages')->nullOnDelete();
            $table->timestamp('pinned_at')->nullable()->after('reply_to_id');
            $table->timestamp('starred_at')->nullable()->after('pinned_at');
            $table->text('note')->nullable()->after('starred_at');
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropForeign(['reply_to_id']);
            $table->dropColumn(['reply_to_id', 'pinned_at', 'starred_at', 'note']);
        });
    }
};
