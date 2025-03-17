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
        Schema::create('shippingTime', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('productId');
            $table->string('Destination')->nullable();
            $table->string('EstimatedTimeDays')->nullable();
            $table->string('status')->default('true');
            $table->timestamps();

            $table->foreign('productId')->references('id')->on('product')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipping_time');
    }
};
