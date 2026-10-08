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
                if (!Schema::hasColumn('leaveapplications', 'ida')) {
                    $table->string('ida')->nullable()->after('id');
                }
                if (!Schema::hasColumn('leaveapplications', 'name')) {
                    $table->string('name')->nullable()->after('ida');
                }
                if (!Schema::hasColumn('leaveapplications', 'type')) {
                    $table->string('type')->nullable()->after('name');
                }
                if (!Schema::hasColumn('leaveapplications', 'date_start')) {
                    $table->date('date_start')->nullable()->after('type');
                }
                if (!Schema::hasColumn('leaveapplications', 'date_end')) {
                    $table->date('date_end')->nullable()->after('date_start');
                }
                if (!Schema::hasColumn('leaveapplications', 'managername')) {
                    $table->string('managername')->nullable()->after('reason');
                }
                if (!Schema::hasColumn('leaveapplications', 'phone')) {
                    $table->string('phone')->nullable()->after('managername');
                }
            });

            DB::statement("UPDATE leaveapplications SET ida = staffid WHERE ida IS NULL AND staffid IS NOT NULL");
            DB::statement("UPDATE leaveapplications SET type = leave_type WHERE type IS NULL AND leave_type IS NOT NULL");
            DB::statement("UPDATE leaveapplications SET date_start = start_date WHERE date_start IS NULL AND start_date IS NOT NULL");
            DB::statement("UPDATE leaveapplications SET date_end = end_date WHERE date_end IS NULL AND end_date IS NOT NULL");
        }
    }

    public function down(): void
    {
    }
};
