<?php

namespace Database\Seeders;

use App\Models\DeliveryFee;
use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class DeliveryFeeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {

        $deliveryFee = new DeliveryFee();
        $deliveryFee->deliveryArea = 'Demo Area';
        $deliveryFee->deliveryFee = 50;
        $deliveryFee->save();

    }
}
