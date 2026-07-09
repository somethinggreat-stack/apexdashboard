<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Brand Tycon Stan's intake form as "Tycoon Duro" (opt-in per-BO branding).
     * Only touches this one business owner; every other BO keeps the generic form.
     */
    public function up(): void
    {
        $updated = DB::table('clients')
            ->where('business_name', 'Tycon Stan')
            ->update(['intake_display_name' => 'Tycoon Duro']);

        // Fallback in case the stored name has different spacing.
        if ($updated === 0) {
            DB::table('clients')
                ->where('business_name', 'like', 'Tycon%Stan%')
                ->update(['intake_display_name' => 'Tycoon Duro']);
        }
    }

    public function down(): void
    {
        DB::table('clients')
            ->where('intake_display_name', 'Tycoon Duro')
            ->update(['intake_display_name' => null]);
    }
};
