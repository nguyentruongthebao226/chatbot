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
        Schema::create('unanswered_questions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('chat_session_id')->nullable();
            $table->unsignedBigInteger('channel_id')->nullable();
            $table->text('question');
            $table->string('questioner')->nullable();
            $table->boolean('answered')->default(false);
            $table->string('answered_by')->nullable();
            $table->timestamps();

            $table->foreign('chat_session_id')->references('id')->on('chat_sessions')->onDelete('set null');
            $table->foreign('channel_id')->references('id')->on('channels')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('unanswered_questions');
    }
};
