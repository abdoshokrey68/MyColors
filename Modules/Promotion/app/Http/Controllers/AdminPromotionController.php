<?php

namespace Modules\Promotion\Http\Controllers;

use App\Http\Controllers\Api\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Promotion\Http\Requests\StorePromotionRequest;
use Modules\Promotion\Http\Requests\UpdatePromotionRequest;
use Modules\Promotion\Http\Resources\PromotionResource;
use Modules\Promotion\Services\AdminPromotionService;

class AdminPromotionController extends BaseController
{
    public function __construct(
        private readonly AdminPromotionService $promotions,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->promotions->list(
            (int) $request->integer('per_page', 15)
        );

        return $this->success([
            'items' => PromotionResource::collection($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ], 'Admin promotions retrieved.');
    }

    public function store(StorePromotionRequest $request): JsonResponse
    {
        $promotion = $this->promotions->create($request->validated());

        return $this->success(
            new PromotionResource($promotion),
            'Promotion created.',
            201
        );
    }

    public function update(UpdatePromotionRequest $request, int $id): JsonResponse
    {
        $promotion = $this->promotions->update($id, $request->validated());

        return $this->success(
            new PromotionResource($promotion),
            'Promotion updated.'
        );
    }

    public function destroy(int $id): JsonResponse
    {
        $this->promotions->delete($id);

        return $this->success(null, 'Promotion deleted.');
    }
}
