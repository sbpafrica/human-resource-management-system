<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('payroll_columns') && !Schema::hasColumn('payroll_columns', 'is_custom')) {
            Schema::table('payroll_columns', function (Blueprint $table) {
                $table->boolean('is_custom')->default(true)->after('client_dbname');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('payroll_columns') && Schema::hasColumn('payroll_columns', 'is_custom')) {
            Schema::table('payroll_columns', function (Blueprint $table) {
                $table->dropColumn('is_custom');
            });
        }
    }
};

