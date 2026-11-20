<?php

namespace Modules\Product\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Product\Enums\ProductStatus;
use Modules\Product\Models\Product;

class ProductDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        Product::factory()->inStock(25)->create([
            'name' => 'Classic White T-Shirt',
            'sku' => 'TSHIRT-WHT-01',
            'description' => 'Soft cotton t-shirt for everyday wear.',
            'price' => 29.99,
        ]);

        Product::factory()->inStock(8)->create([
            'name' => 'Navy Hoodie',
            'sku' => 'HOODIE-NVY-01',
            'description' => 'Warm fleece hoodie with front pocket.',
            'price' => 79.50,
        ]);

        Product::factory()->outOfStock()->create([
            'name' => 'Limited Sneakers',
            'sku' => 'SNEAK-LTD-01',
            'description' => 'Sold out limited edition sneakers.',
            'price' => 149.00,
        ]);

        Product::factory()->create([
            'name' => 'Draft Cap',
            'sku' => 'CAP-DRAFT-01',
            'description' => 'Inactive product not listed publicly.',
            'price' => 19.00,
            'stock_quantity' => 5,
            'status' => ProductStatus::Inactive,
        ]);

        Product::factory()->count(12)->create();
    }
}
