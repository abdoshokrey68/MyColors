<?php

namespace Modules\Order\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrderIndexPerPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_orders_index_rejects_per_page_over_limit(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/orders?per_page=101')
            ->assertUnprocessable();
    }
}
