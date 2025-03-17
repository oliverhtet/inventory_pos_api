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
        Schema::create('deliveryChallan', function (Blueprint $table) {
            $table->id();
            $table->uuid('saleInvoiceId');
            $table->string('challanNo')->unique();
            $table->string('challanDate');
            $table->string('challanNote');
            $table->string('vehicleNo');
            $table->string('status')->default('true');
            $table->timestamps();

            $table->foreign('saleInvoiceId')->references('id')->on('saleInvoice')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deliveryChallan');
    }
};
