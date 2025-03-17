<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use \App\Models\EmailConfig;

class EmailConfigSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $emailConfig = new EmailConfig();
        $emailConfig->emailConfigName = 'InventoryOs';
        $emailConfig->emailHost = 'mail.lfix.us';
        $emailConfig->emailPort = '465';
        $emailConfig->emailUser = 'no-reply@lfix.us';
        $emailConfig->emailPass = '@omega@2020@omega';
        $emailConfig->save();
    }
}
