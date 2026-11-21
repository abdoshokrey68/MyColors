<?php

namespace Modules\Promotion\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Promotion\Enums\PromotionType;
use Modules\Promotion\Models\Promotion;
use Tests\TestCase;

class AdminPromotionApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_promotion(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $response = $this->postJson('/api/admin/promotions', [
            'code' => 'WELCOME10',
            'type' => PromotionType::Percentage->value,
            'value' => 10,
            'min_cart_amount' => 50,
            'max_discount_amount' => 25,
            'is_active' => true,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.code', 'WELCOME10');

        $this->assertDatabaseHas('promotions', ['code' => 'WELCOME10']);
    }

    public function test_admin_can_list_and_delete_promotion(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $promotion = Promotion::factory()->create(['code' => 'DELETEME']);

        $this->getJson('/api/admin/promotions')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1);

        $this->deleteJson('/api/admin/promotions/'.$promotion->id)
            ->assertOk();

        $this->assertDatabaseMissing('promotions', ['id' => $promotion->id]);
    }
}
