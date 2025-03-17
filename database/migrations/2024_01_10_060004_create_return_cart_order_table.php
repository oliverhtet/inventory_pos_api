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
        Schema::create('returnCartOrder', function (Blueprint $table) {
            $table->id();
            $table->uuid('cartOrderId');
            $table->dateTime('date');
            $table->double('totalAmount');
            $table->double('totalVatAmount')->nullable();
            $table->double('totalDiscountAmount')->nullable();
            $table->text('note')->nullable();
            $table->double('couponAmount')->nullable();
            $table->enum('returnType', ['PRODUCT', 'REFUND']);
            $table->enum('returnCartOrderStatus', ['PENDING', 'RECEIVED', 'REFUNDED', 'RESEND', 'RESENDED', 'REJECTED'])->default('PENDING');
            $table->string('status')->default('true');
            $table->timestamps();

            $table->foreign('cartOrderId')->references('id')->on('cartOrder')->onDelete('cascade')->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('returnCartOrder');
    }
};
