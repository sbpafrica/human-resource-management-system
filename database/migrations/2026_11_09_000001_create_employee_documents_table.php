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
        if (!Schema::hasTable('employee_documents')) {
            Schema::create('employee_documents', function (Blueprint $table) {
                $table->id();
                $table->string('staff_id', 50)->index();
                $table->string('employee_name', 200)->nullable();
                $table->string('client_name', 150)->nullable()->index();
                $table->string('document_type', 100)->default('General Document');
                $table->string('document_title', 255);
                $table->string('file_name', 255);
                $table->string('file_path', 500);
                $table->unsignedBigInteger('file_size')->nullable();
                $table->string('file_mime', 100)->nullable();
                $table->string('recipient_email', 255)->index();
                $table->unsignedBigInteger('sender_user_id')->nullable()->index();
                $table->string('sender_email', 255)->nullable();
                $table->enum('status', ['sent', 'failed', 'pending'])->default('sent');
                $table->text('error_message')->nullable();
                $table->text('notes')->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_documents');
    }
};
