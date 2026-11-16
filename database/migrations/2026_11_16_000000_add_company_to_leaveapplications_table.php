<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('leaveapplications')) {
            Schema::table('leaveapplications', function (Blueprint $table) {
                if (!Schema::hasColumn('leaveapplications', 'company')) {
                    $table->string('company', 255)->nullable()->after('name');
                }
            });

            // Backfill company from newemployeedata or employeecontact where available
            try {
                DB::statement("
                    UPDATE leaveapplications la
                    LEFT JOIN newemployeedata ned ON (la.staffid = ned.staffid OR la.ida = ned.staffid)
                    LEFT JOIN employeecontact ec ON (la.staffid = ec.staffid OR la.ida = ec.staffid)
                    SET la.company = COALESCE(NULLIF(ned.company, ''), NULLIF(ec.shop, ''), la.company)
                    WHERE (la.company IS NULL OR la.company = '')
                ");
            } catch (\Throwable $e) {
                // Ignore backfill errors if tables do not exist
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('leaveapplications') && Schema::hasColumn('leaveapplications', 'company')) {
            Schema::table('leaveapplications', function (Blueprint $table) {
                $table->dropColumn('company');
            });
        }
    }
};
