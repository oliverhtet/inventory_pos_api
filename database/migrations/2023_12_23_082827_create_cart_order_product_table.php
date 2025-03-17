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
        Schema::create('cartOrderProduct', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('productId');
            $table->uuid('invoiceId');
            $table->unsignedBigInteger('colorId')->nullable();
            $table->integer('productQuantity');
            $table->double('productSalePrice');
            $table->integer('productVat')->nullable();
            $table->string('discountType')->nullable();
            $table->integer('discount')->nullable();
            $table->timestamps();

            $table->foreign('productId')->references('id')->on('product');
            $table->foreign('invoiceId')->references('id')->on('cartOrder')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('colorId')->references('id')->on('colors');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cartOrderProduct');
    }
};
