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
        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('chat_session_id');
            $table->unsignedBigInteger('bot_id');
            $table->string('sender')->nullable();
            $table->text('question');
            $table->longText('answer')->nullable();
            $table->unsignedBigInteger('embedding_id')->nullable();
            $table->json('question_embedding')->nullable();
            $table->string('question_qdrant_id')->nullable();
            $table->timestamps();

            $table->foreign('chat_session_id')->references('id')->on('chat_sessions')->onDelete('cascade');
            $table->foreign('bot_id')->references('id')->on('bots_ai')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
    }
};
