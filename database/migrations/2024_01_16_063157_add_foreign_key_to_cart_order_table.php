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
        Schema::table('cartOrder', function (Blueprint $table) {
            //
            $table->unsignedBigInteger('courierMediumId')->nullable();
            $table->unsignedBigInteger('deliveryFeeId')->nullable();
            $table->uuid('previousCartOrderId')->nullable();

            $table->foreign('courierMediumId')->references('id')->on('courierMedium');
            $table->foreign('deliveryFeeId')->references('id')->on('deliveryFee')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cartOrder', function (Blueprint $table) {
            $table->dropColumn('courierMediumId');
            $table->dropColumn('deliveryFeeId');
        });
    }
};
