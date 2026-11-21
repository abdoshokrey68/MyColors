<?php

namespace Modules\Product\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Product\Enums\ProductStatus;
use Modules\Product\Models\Product;

class ProductDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $catalog = [
            [
                'sku' => 'TSHIRT-WHT-01',
                'name' => 'Classic White T-Shirt',
                'description' => 'Soft cotton t-shirt for everyday wear.',
                'price' => 29.99,
                'stock_quantity' => 25,
                'status' => ProductStatus::Active,
            ],
            [
                'sku' => 'HOODIE-NVY-01',
                'name' => 'Navy Hoodie',
                'description' => 'Warm fleece hoodie with front pocket.',
                'price' => 79.50,
                'stock_quantity' => 8,
                'status' => ProductStatus::Active,
            ],
            [
                'sku' => 'SNEAK-LTD-01',
                'name' => 'Limited Sneakers',
                'description' => 'Sold out limited edition sneakers.',
                'price' => 149.00,
                'stock_quantity' => 0,
                'status' => ProductStatus::Active,
            ],
            [
                'sku' => 'CAP-DRAFT-01',
                'name' => 'Draft Cap',
                'description' => 'Inactive product not listed publicly.',
                'price' => 19.00,
                'stock_quantity' => 5,
                'status' => ProductStatus::Inactive,
            ],
            [
                'sku' => 'JEANS-BLU-01',
                'name' => 'Slim Blue Jeans',
                'description' => 'Stretch denim with a modern slim fit.',
                'price' => 64.00,
                'stock_quantity' => 40,
                'status' => ProductStatus::Active,
            ],
            [
                'sku' => 'SOCK-WHT-3PK',
                'name' => 'White Socks 3-Pack',
                'description' => 'Breathable everyday socks.',
                'price' => 12.50,
                'stock_quantity' => 100,
                'status' => ProductStatus::Active,
            ],
        ];

        foreach ($catalog as $product) {
            Product::query()->updateOrCreate(
                ['sku' => $product['sku']],
                $product
            );
        }

        if (Product::query()->count() < 15) {
            Product::factory()
                ->count(15 - Product::query()->count())
                ->create();
        }
    }
}
