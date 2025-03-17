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
        Schema::create('cartOrderAttributeValue', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cartOrderProductId');
            $table->unsignedBigInteger('productAttributeValueId');
            $table->string('status')->default('true');
            $table->timestamps();

            $table->foreign('cartOrderProductId')->references('id')->on('cartOrderProduct');
            $table->foreign('productAttributeValueId')->references('id')->on('productAttributeValue');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cartOrderAttributeValue');
    }
};
