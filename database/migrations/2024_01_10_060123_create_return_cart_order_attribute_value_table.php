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
        Schema::create('returnCartOrderAttributeValue', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('returnCartOrderProductId');
            $table->unsignedBigInteger('productAttributeValueId');
            $table->string('status')->default('true');
            $table->timestamps();

            $table->foreign('returnCartOrderProductId')->references('id')->on('returnCartOrderProduct');
            $table->foreign('productAttributeValueId')->references('id')->on('productAttributeValue');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('returnCartOrderAttributeValue');
    }
};
