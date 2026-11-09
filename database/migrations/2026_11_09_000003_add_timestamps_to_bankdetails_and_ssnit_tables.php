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
        if (Schema::hasTable('bankdetails')) {
            Schema::table('bankdetails', function (Blueprint $table) {
                if (!Schema::hasColumn('bankdetails', 'created_at')) {
                    $table->timestamp('created_at')->nullable()->index();
                }
                if (!Schema::hasColumn('bankdetails', 'updated_at')) {
                    $table->timestamp('updated_at')->nullable();
                }
            });
        }

        if (Schema::hasTable('ssnit')) {
            Schema::table('ssnit', function (Blueprint $table) {
                if (!Schema::hasColumn('ssnit', 'created_at')) {
                    $table->timestamp('created_at')->nullable()->index();
                }
                if (!Schema::hasColumn('ssnit', 'updated_at')) {
                    $table->timestamp('updated_at')->nullable();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('bankdetails')) {
            Schema::table('bankdetails', function (Blueprint $table) {
                if (Schema::hasColumn('bankdetails', 'created_at')) {
                    $table->dropColumn(['created_at', 'updated_at']);
                }
            });
        }

        if (Schema::hasTable('ssnit')) {
            Schema::table('ssnit', function (Blueprint $table) {
                if (Schema::hasColumn('ssnit', 'created_at')) {
                    $table->dropColumn(['created_at', 'updated_at']);
                }
            });
        }
    }
};
