<?php

namespace Modules\Order\Services;

use App\Models\User;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Order\Enums\IdempotencyKeyStatus;
use Modules\Order\Exceptions\IdempotencyConflictException;
use Modules\Order\Exceptions\IdempotencyInProgressException;
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

        return DB::transaction(function () use ($user, $key, $requestHash, $callback) {
            $record = $this->reserveOrReplay($user->id, $key, $requestHash);

            if ($record instanceof JsonResponse) {
                return $record;
            }

            [$order, $response] = $callback();

            $record->update([
                'status' => IdempotencyKeyStatus::Completed,
                'response_code' => $response->getStatusCode(),
                'response_body' => $response->getData(true),
                'order_id' => $order->id,
            ]);

            return $response;
        }, 5);
    }

    private function reserveOrReplay(int $userId, string $key, string $requestHash): IdempotencyKey|JsonResponse
    {
        try {
            return IdempotencyKey::query()->create([
                'user_id' => $userId,
                'key' => $key,
                'request_hash' => $requestHash,
                'status' => IdempotencyKeyStatus::Processing,
                'response_code' => null,
                'response_body' => null,
                'order_id' => null,
            ]);
        } catch (UniqueConstraintViolationException) {
            $existing = IdempotencyKey::query()
                ->where('user_id', $userId)
                ->where('key', $key)
                ->lockForUpdate()
                ->first();

            if (! $existing) {
                throw new IdempotencyConflictException('Idempotency-Key could not be reserved.');
            }

            return $this->replayOrConflict($existing, $requestHash);
        }
    }

    private function replayOrConflict(IdempotencyKey $existing, string $requestHash): JsonResponse
    {
        if (! hash_equals($existing->request_hash, $requestHash)) {
            throw new IdempotencyConflictException;
        }

        if ($existing->status === IdempotencyKeyStatus::Processing) {
            throw new IdempotencyInProgressException;
        }

        return response()->json($existing->response_body, (int) $existing->response_code);
    }
}
