<?php

namespace Database\Seeders;

use App\Actions\Demo\ProvisionDemoEnvironment;
use Illuminate\Database\Seeder;

class DemoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(ProvisionDemoEnvironment $provisionDemoEnvironment): void
    {
        $provisionDemoEnvironment->handle();
    }
}
