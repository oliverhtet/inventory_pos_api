<?php

namespace Database\Seeders;

use App\Models\EcomSetting;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ecomSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $ecom = new EcomSetting();
        $ecom->isActive = 'true';
        $ecom->save();
    }
}
