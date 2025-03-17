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
        Schema::create('reviewReply', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('reviewId');
            $table->unsignedBigInteger('adminId')->nullable();
            $table->longText('comment')->nullable();
            $table->string('status')->default('true');
            $table->timestamps();

            $table->foreign('reviewId')->references('id')->on('reviewRating')->onDelete('cascade');
            $table->foreign('adminId')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reviewReply');
    }
};
