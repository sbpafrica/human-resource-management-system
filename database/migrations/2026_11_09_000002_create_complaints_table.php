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
        if (!Schema::hasTable('complaints')) {
            Schema::create('complaints', function (Blueprint $table) {
                $table->id();
                $table->string('ticket_number', 50)->unique();
                $table->string('client_name', 150)->index();
                $table->string('staff_id', 50)->index();
                $table->string('employee_name', 200);
                $table->string('shop_name', 150)->nullable()->index();
                $table->string('phone', 50)->nullable();
                $table->string('email', 150)->nullable();
                $table->string('month_period', 50)->nullable()->index(); // e.g. "October 2025" or "2026-10"
                $table->string('category', 100)->default('Salary Discrepancy')->index();
                $table->text('complaint_text');
                $table->string('attachment_path', 500)->nullable();
                $table->enum('status', ['pending', 'under_review', 'resolved', 'rejected'])->default('pending')->index();
                $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium');
                $table->unsignedBigInteger('resolved_by')->nullable()->index();
                $table->text('resolution_notes')->nullable();
                $table->timestamp('resolved_at')->nullable();
                $table->string('submission_source', 50)->default('portal'); // portal, hr_manual, legacy_import
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('complaints');
    }
};
