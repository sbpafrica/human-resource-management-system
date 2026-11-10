<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('complaints')
            ->where(function ($q) {
                $q->where('month_period', 'like', '%October 2026%')
                  ->orWhere('month_period', 'like', '%October%26%')
                  ->orWhere('month_period', 'October')
                  ->orWhere('ticket_number', 'like', '%october26%')
                  ->orWhere('ticket_number', 'like', '%oct26%');
            })
            ->update([
                'status'           => 'pending',
                'resolution_notes' => null,
                'resolved_by'      => null,
                'resolved_at'      => null,
                'updated_at'       => now(),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No-op
    }
};
