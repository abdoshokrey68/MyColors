<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Modules\Product\Database\Seeders\ProductDatabaseSeeder;
use Modules\Promotion\Database\Seeders\PromotionDatabaseSeeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        User::factory()->create([
            'name' => 'Test Customer',
            'email' => 'customer@example.com',
            'password' => 'password',
            'is_admin' => false,
        ]);

        User::factory()->admin()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);

        $this->call([
            ProductDatabaseSeeder::class,
            PromotionDatabaseSeeder::class,
        ]);
    }
}
