<?php

namespace Modules\Order\Services;

use App\Models\User;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Order\Exceptions\IdempotencyConflictException;
use Modules\Order\Models\IdempotencyKey;
use Modules\Order\Models\Order;

class IdempotencyService
{
    /**
     * @param  Closure(): array{0: Order, 1: JsonResponse}  $callback
     */
    public function remember(User $user, string $key, Request $request, Closure $callback): JsonResponse
    {
        $requestHash = hash('sha256', json_encode([
            'path' => $request->path(),
            'method' => $request->method(),
            'payload' => $request->all(),
        ]));

        $existing = $this->findExisting($user->id, $key);

        if ($existing) {
            return $this->replayOrConflict($existing, $requestHash);
        }

        [$order, $response] = $callback();

        try {
            IdempotencyKey::query()->create([
                'user_id' => $user->id,
                'key' => $key,
                'request_hash' => $requestHash,
                'response_code' => $response->getStatusCode(),
                'response_body' => $response->getData(true),
                'order_id' => $order->id,
            ]);
        } catch (UniqueConstraintViolationException) {
            $raceWinner = $this->findExisting($user->id, $key);

            if ($raceWinner) {
                return $this->replayOrConflict($raceWinner, $requestHash);
            }
        }

        return $response;
    }

    private function findExisting(int $userId, string $key): ?IdempotencyKey
    {
        return IdempotencyKey::query()
            ->where('user_id', $userId)
            ->where('key', $key)
            ->first();
    }

    private function replayOrConflict(IdempotencyKey $existing, string $requestHash): JsonResponse
    {
        if (! hash_equals($existing->request_hash, $requestHash)) {
            throw new IdempotencyConflictException;
        }

        return response()->json($existing->response_body, $existing->response_code);
    }
}
