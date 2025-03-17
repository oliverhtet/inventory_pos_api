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
        Schema::create('reviewRating', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('productId');
            $table->unsignedBigInteger('customerId');
            $table->integer('rating')->nullable();
            $table->string('review')->nullable();
            $table->string('status')->default('true');
            $table->timestamps();
            // foreign key relation constraints
            $table->foreign('productId')->references('id')->on('product');
            $table->foreign('customerId')->references('id')->on('customer');
           
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reviewRating');
    }
};
