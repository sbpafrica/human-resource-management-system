<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('assistant_threads')) {
            Schema::create('assistant_threads', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->index();
                $table->string('title')->default('New Conversation');
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('assistant_messages')) {
            Schema::create('assistant_messages', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('thread_id')->index();
                $table->string('role'); // user, assistant, system, tool
                $table->longText('content')->nullable();
                $table->json('tool_calls')->nullable();
                $table->string('tool_call_id')->nullable();
                $table->timestamps();

                $table->foreign('thread_id')
                    ->references('id')
                    ->on('assistant_threads')
                    ->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('assistant_messages');
        Schema::dropIfExists('assistant_threads');
    }
};
