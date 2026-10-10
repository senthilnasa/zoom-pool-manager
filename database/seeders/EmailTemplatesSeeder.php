<?php

namespace Database\Seeders;

use App\Domain\Communication\Services\EmailTemplateRegistry;
use Illuminate\Database\Seeder;

class EmailTemplatesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        EmailTemplateRegistry::resetAllToDefaults();
    }
}
