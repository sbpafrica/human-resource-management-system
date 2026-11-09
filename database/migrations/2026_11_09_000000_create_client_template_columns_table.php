<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('client_template_columns')) {
            Schema::create('client_template_columns', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('client_id')->nullable();
                $table->string('client_dbname', 150);
                $table->string('module', 50)->default('payroll'); // payroll, ssnit, petra, bank
                $table->string('column_key', 100);
                $table->string('display_label', 150);
                $table->text('header_aliases')->nullable();
                $table->string('data_type', 50)->default('decimal');
                $table->boolean('is_active')->default(true);
                $table->boolean('is_required')->default(false);
                $table->boolean('is_custom')->default(false);
                $table->integer('sort_order')->default(0);
                $table->string('sample_value', 255)->nullable();
                $table->timestamps();

                $table->index(['client_dbname', 'module']);
                $table->index(['client_id', 'module']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('client_template_columns');
    }
};
