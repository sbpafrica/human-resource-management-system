<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('payroll_columns') && !Schema::hasColumn('payroll_columns', 'header_aliases')) {
            Schema::table('payroll_columns', function (Blueprint $table) {
                $table->text('header_aliases')->nullable()->after('display_label');
            });

            // Seed default aliases for Shefer's custom columns
            DB::table('payroll_columns')
                ->where('column_name', 'total_cash_emolument')
                ->whereNull('header_aliases')
                ->update([
                    'header_aliases' => 'Total Cash Emolument, total_cash_emolument, Total Cash Emoluments, Cash Emoluments, Cash Emolument, Total Cash'
                ]);

            DB::table('payroll_columns')
                ->where('column_name', 'total_assessable_income')
                ->whereNull('header_aliases')
                ->update([
                    'header_aliases' => 'Total Assessable Income, total_assessable_income, Assessable Income, Total Assessable'
                ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('payroll_columns') && Schema::hasColumn('payroll_columns', 'header_aliases')) {
            Schema::table('payroll_columns', function (Blueprint $table) {
                $table->dropColumn('header_aliases');
            });
        }
    }
};

