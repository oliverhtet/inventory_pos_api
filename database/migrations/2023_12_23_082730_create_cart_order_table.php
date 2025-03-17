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
        Schema::create('cartOrder', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->dateTime('date');
            $table->double('totalAmount');
            $table->double('paidAmount')->nullable();
            $table->double('deliveryFee')->nullable();
            $table->double('due')->nullable();
            $table->string('isPaid')->default('false');
            $table->double('profit');
            $table->unsignedBigInteger('couponId')->nullable();
            $table->double('couponAmount')->nullable();
            $table->unsignedBigInteger('customerId');
            $table->unsignedBigInteger('userId');
            $table->string('deliveryAddress')->nullable();
            $table->string('customerPhone')->nullable();
            $table->string('note')->nullable();
            $table->string('isReOrdered')->default('false');
            $table->enum('orderStatus', ['PENDING', 'RECEIVED', 'SHIPPED', 'DELIVERED', 'RETURNED', 'CANCELLED'])->default('PENDING');
            $table->string('status')->default('true');
            $table->timestamps();

            // foreign key
            $table->foreign('customerId')->references('id')->on('customer')->onDelete('cascade');
            $table->foreign('userId')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('couponId')->references('id')->on('coupon');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cartOrder');
    }
};
