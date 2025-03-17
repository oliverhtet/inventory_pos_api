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
        Schema::create('productWishlist', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('wishlistId');
            $table->unsignedBigInteger('productId');
            $table->string('status')->default('true');
            $table->timestamps();

            $table->foreign('wishlistId')->references('id')->on('wishlist');
            $table->foreign('productId')->references('id')->on('product');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_wishlist');
    }
};
