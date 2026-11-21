<?php

namespace Modules\Promotion\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Promotion\Enums\PromotionType;

class UpdatePromotionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $promotionId = (int) $this->route('id');

        return [
            'code' => ['sometimes', 'string', 'max:50', Rule::unique('promotions', 'code')->ignore($promotionId)],
            'type' => ['sometimes', Rule::enum(PromotionType::class)],
            'value' => ['sometimes', 'numeric', 'min:0'],
            'min_cart_amount' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'max_discount_amount' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'starts_at' => ['sometimes', 'nullable', 'date'],
            'ends_at' => ['sometimes', 'nullable', 'date'],
            'usage_limit' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'per_customer_usage_limit' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
