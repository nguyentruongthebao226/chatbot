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
        Schema::create('document_chunks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('document_id');
            $table->longText('content');
            $table->json('embedding')->nullable();
            $table->string('qdrant_id')->nullable();
            $table->string('vector_collection')->nullable();
            $table->timestamps();

            $table->foreign('document_id')->references('id')->on('training_documents')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_chunks');
    }
};
