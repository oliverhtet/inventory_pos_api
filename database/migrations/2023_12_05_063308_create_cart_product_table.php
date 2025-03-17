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
        Schema::create('cartProduct', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cartId');
            $table->unsignedBigInteger('productId');
            $table->unsignedBigInteger('colorId')->nullable();
            $table->integer('productQuantity');
            $table->timestamps();

            $table->foreign('cartId')->references('id')->on('cart');
            $table->foreign('productId')->references('id')->on('product');
            $table->foreign('colorId')->references('id')->on('colors');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cartProduct');
    }
};
