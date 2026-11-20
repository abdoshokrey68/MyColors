<?php

namespace Modules\Cart\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Cart\Models\CartItem;
use Modules\Product\Http\Resources\ProductResource;

/**
 * @mixin CartItem
 */
class CartItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'quantity' => $this->quantity,
            'unit_price' => $this->product->price,
            'line_total' => number_format($this->lineTotal(), 2, '.', ''),
            'product' => new ProductResource($this->product),
        ];
    }
}
