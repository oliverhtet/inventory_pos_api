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
        Schema::create('cartAttributeValue', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cartProductId');
            $table->unsignedBigInteger('productAttributeValueId');
            $table->string('status')->default('true');
            $table->timestamps();

            $table->foreign('cartProductId')->references('id')->on('cartProduct');
            $table->foreign('productAttributeValueId')->references('id')->on('productAttributeValue');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cart_attribute_value');
    }
};
