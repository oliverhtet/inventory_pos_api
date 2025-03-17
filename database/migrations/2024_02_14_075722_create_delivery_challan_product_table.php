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
        Schema::create('deliveryChallanProduct', function (Blueprint $table) {
            $table->id();
            $table->string('deliveryChallanId');
            $table->unsignedBigInteger('productId');
            $table->integer('quantity');
            $table->timestamps();
            $table->foreign('deliveryChallanId')->references('challanNo')->on('deliveryChallan')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deliveryChallanProduct');
    }
};
