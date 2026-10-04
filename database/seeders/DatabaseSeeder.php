<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Run the ManagerSeeder
        $this->call([
            ManagerSeeder::class,
            unitSeeder::class,
            TenantSeeder::class,
        ]);
    }
}
