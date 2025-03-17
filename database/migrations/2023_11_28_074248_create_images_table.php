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
        Schema::create('images', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('productId')->nullable();
            $table->unsignedBigInteger('reviewId')->nullable();
            $table->string('imageName');
            $table->string('status')->default("true");
            $table->timestamps();

            // foreign key constraints and relation
            $table->foreign('productId')->references('id')->on('product');
            $table->foreign('reviewId')->references('id')->on('reviewRating');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('images');
    }
};
