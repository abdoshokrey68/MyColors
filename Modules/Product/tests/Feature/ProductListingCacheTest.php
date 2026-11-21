<?php

namespace Modules\Product\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Modules\Product\Models\Product;
use Modules\Product\Services\ProductService;
use Tests\TestCase;

class ProductListingCacheTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_listing_bumps_cache_version_on_flush(): void
    {
        Product::factory()->count(2)->create();

        $this->getJson('/api/products')->assertOk();

        $versionBefore = Cache::get('products:list:version', 1);

        app(ProductService::class)->flushListingCache();

        $this->assertNotSame($versionBefore, Cache::get('products:list:version'));

        $this->getJson('/api/products')
            ->assertOk()
            ->assertJsonPath('success', true);
    }
}
