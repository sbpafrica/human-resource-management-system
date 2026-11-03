<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('payroll_columns')) {
            Schema::create('payroll_columns', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('client_id')->nullable()->index();
                $table->string('client_dbname', 64)->index();
                $table->string('column_name', 64);
                $table->string('display_label', 128);
                $table->string('data_type', 32)->default('decimal(15,2)');
                $table->string('position_after', 64)->nullable();
                $table->boolean('show_in_payslip')->default(false);
                $table->string('payslip_section', 32)->default('earnings'); // earnings, deductions, tax_reference, info
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['client_dbname', 'column_name']);
            });

            // Seed Shefer's known custom columns so they appear immediately
            DB::table('payroll_columns')->insertOrIgnore([
                [
                    'client_dbname'    => 'shefer',
                    'column_name'      => 'total_cash_emolument',
                    'display_label'    => 'Total Cash Emolument',
                    'data_type'        => 'decimal(15,2)',
                    'position_after'   => 'gross',
                    'show_in_payslip'  => true,
                    'payslip_section'  => 'tax_reference',
                    'is_active'        => true,
                    'created_at'       => now(),
                    'updated_at'       => now(),
                ],
                [
                    'client_dbname'    => 'shefer',
                    'column_name'      => 'total_assessable_income',
                    'display_label'    => 'Total Assessable Income',
                    'data_type'        => 'decimal(15,2)',
                    'position_after'   => 'total_cash_emolument',
                    'show_in_payslip'  => true,
                    'payslip_section'  => 'tax_reference',
                    'is_active'        => true,
                    'created_at'       => now(),
                    'updated_at'       => now(),
                ],
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_columns');
    }
};

