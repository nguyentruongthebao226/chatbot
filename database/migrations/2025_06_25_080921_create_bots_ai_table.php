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
        Schema::create('bots_ai', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('staff_id');
            $table->string('icon')->nullable();
            $table->timestamps();

            $table->foreign('staff_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bots_ai');
    }
};
