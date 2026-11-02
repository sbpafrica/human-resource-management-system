<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        try {
            $tables = DB::select("SHOW TABLES LIKE 'twellium%'");
            $schema = Schema::connection(config('database.default'));

            foreach ($tables as $t) {
                $tableName = array_values((array)$t)[0];

                // Target monthly payroll tables (e.g. twelliumoctober26, twelliumseptember25, etc.)
                if (preg_match('/^twellium(january|february|march|april|may|june|july|august|september|october|november|december)\d{2}$/i', $tableName)) {
                    if (!$schema->hasColumn($tableName, 'total_cash_emolument')) {
                        if ($schema->hasColumn($tableName, 'allowance')) {
                            DB::statement("ALTER TABLE `{$tableName}` ADD COLUMN `total_cash_emolument` DECIMAL(12,2) NULL DEFAULT NULL AFTER `allowance`");
                        } else {
                            DB::statement("ALTER TABLE `{$tableName}` ADD COLUMN `total_cash_emolument` DECIMAL(12,2) NULL DEFAULT NULL");
                        }
                    }

                    if (!$schema->hasColumn($tableName, 'total_assessable_income')) {
                        if ($schema->hasColumn($tableName, 'deduction')) {
                            DB::statement("ALTER TABLE `{$tableName}` ADD COLUMN `total_assessable_income` DECIMAL(12,2) NULL DEFAULT NULL AFTER `deduction`");
                        } else {
                            DB::statement("ALTER TABLE `{$tableName}` ADD COLUMN `total_assessable_income` DECIMAL(12,2) NULL DEFAULT NULL");
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Migration add_total_cash_and_assessable_to_twellium_tables failed: ' . $e->getMessage());
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        try {
            $tables = DB::select("SHOW TABLES LIKE 'twellium%'");
            $schema = Schema::connection(config('database.default'));

            foreach ($tables as $t) {
                $tableName = array_values((array)$t)[0];

                if (preg_match('/^twellium(january|february|march|april|may|june|july|august|september|october|november|december)\d{2}$/i', $tableName)) {
                    if ($schema->hasColumn($tableName, 'total_cash_emolument')) {
                        DB::statement("ALTER TABLE `{$tableName}` DROP COLUMN `total_cash_emolument`");
                    }
                    if ($schema->hasColumn($tableName, 'total_assessable_income')) {
                        DB::statement("ALTER TABLE `{$tableName}` DROP COLUMN `total_assessable_income`");
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Migration rollback add_total_cash_and_assessable_to_twellium_tables failed: ' . $e->getMessage());
        }
    }
};
