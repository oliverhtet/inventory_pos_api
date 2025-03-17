<?php

namespace Database\Seeders;

use App\Models\SubAccount;
use App\Models\CourierMedium;
use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class CourierMediumSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {

        $subAccount = new SubAccount();
        $subAccount->name = 'Demo Courier';
        $subAccount->accountId = 1;
        $subAccount->save();
        $courierMedium = new CourierMedium();
        $courierMedium->courierMediumName = 'Demo Courier';
        $courierMedium->address = 'Demo Address';
        $courierMedium->phone = '1234567890';
        $courierMedium->email = 'demo@gmail.com';
        $courierMedium->type = 'courier';
        $courierMedium->subAccountId = $subAccount->id;
        $courierMedium->save();

    }
}
