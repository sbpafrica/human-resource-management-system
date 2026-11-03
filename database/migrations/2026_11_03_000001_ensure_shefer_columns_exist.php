<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Ensures total_cash_emolument and total_assessable_income exist on all existing Shefer tables,
     * and syncs all registered custom payroll columns.
     */
    public function up(): void
    {
        try {
            $tables = DB::select("
                SELECT TABLE_NAME 
                FROM INFORMATION_SCHEMA.TABLES 
                WHERE TABLE_SCHEMA = DATABASE() 
                  AND (TABLE_NAME = 'shefer' OR TABLE_NAME LIKE 'shefer%')
                  AND TABLE_NAME NOT LIKE '%_sign'
                  AND TABLE_NAME NOT LIKE '%_contracts%'
                  AND TABLE_NAME NOT LIKE '%attendance%'
                  AND TABLE_NAME NOT LIKE '%con'
                  AND TABLE_NAME NOT LIKE '%data'
                  AND TABLE_NAME NOT LIKE '%sms%'
            ");

            $schema = DB::getSchemaBuilder();

            foreach ($tables as $t) {
                $tableName = $t->TABLE_NAME;
                if (!$schema->hasTable($tableName)) {
                    continue;
                }

                if (!$schema->hasColumn($tableName, 'total_cash_emolument')) {
                    try {
                        DB::statement("ALTER TABLE `{$tableName}` ADD COLUMN `total_cash_emolument` DECIMAL(15,2) NULL DEFAULT NULL AFTER `gross`");
                    } catch (\Throwable $e) {
                        try {
                            DB::statement("ALTER TABLE `{$tableName}` ADD COLUMN `total_cash_emolument` DECIMAL(15,2) NULL DEFAULT NULL");
                        } catch (\Throwable $e2) {
                            Log::warning("Could not add total_cash_emolument to {$tableName}: " . $e2->getMessage());
                        }
                    }
                }

                if (!$schema->hasColumn($tableName, 'total_assessable_income')) {
                    try {
                        DB::statement("ALTER TABLE `{$tableName}` ADD COLUMN `total_assessable_income` DECIMAL(15,2) NULL DEFAULT NULL AFTER `total_cash_emolument`");
                    } catch (\Throwable $e) {
                        try {
                            DB::statement("ALTER TABLE `{$tableName}` ADD COLUMN `total_assessable_income` DECIMAL(15,2) NULL DEFAULT NULL");
                        } catch (\Throwable $e2) {
                            Log::warning("Could not add total_assessable_income to {$tableName}: " . $e2->getMessage());
                        }
                    }
                }

                if (class_exists(\App\Models\PayrollColumn::class)) {
                    \App\Models\PayrollColumn::ensureColumnsForTable($tableName, 'shefer');
                }
            }
        } catch (\Throwable $e) {
            Log::error("Migration ensure_shefer_columns_exist error: " . $e->getMessage());
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Safe no-op
    }
};

