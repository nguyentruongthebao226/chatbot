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
        Schema::create('training_documents', function (Blueprint $table) {
            $table->id();
            $table->string('type')->nullable();
            $table->string('name');
            $table->longText('content')->nullable();
            $table->unsignedBigInteger('bot_id');
            $table->timestamps();

            $table->foreign('bot_id')->references('id')->on('bots_ai')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('training_documents');
    }
};
