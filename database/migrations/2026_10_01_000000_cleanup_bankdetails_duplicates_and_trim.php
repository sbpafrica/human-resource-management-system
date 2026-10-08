<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        try {
            // Delete duplicate untrimmed rows where a clean row already exists
            DB::statement("
                DELETE b1 FROM bankdetails b1
                INNER JOIN bankdetails b2
                  ON  TRIM(b1.employid) = TRIM(b2.employid)
                  AND b1.employid != TRIM(b1.employid)
                  AND b2.employid  = TRIM(b2.employid)
            ");

            // Trim all fields for remaining records
            DB::statement("
                UPDATE bankdetails SET
                  employid = TRIM(employid),
                  name     = TRIM(name),
                  shop     = TRIM(shop),
                  bank     = TRIM(bank),
                  account  = TRIM(account),
                  code     = TRIM(code),
                  branch   = TRIM(branch),
                  momonum  = TRIM(momonum),
                  momoname = TRIM(momoname)
            ");
        } catch (\Throwable $e) {
            Log::warning('Migration bankdetails cleanup: ' . $e->getMessage());
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No reversal needed for data trimming/deduplication
    }
};
