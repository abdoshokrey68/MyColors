<?php

namespace Modules\Order\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Order\Enums\IdempotencyKeyStatus;

class IdempotencyKey extends Model
{
    protected $fillable = [
        'user_id',
        'key',
        'request_hash',
        'status',
        'response_code',
        'response_body',
        'order_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => IdempotencyKeyStatus::class,
            'response_body' => 'array',
            'response_code' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
