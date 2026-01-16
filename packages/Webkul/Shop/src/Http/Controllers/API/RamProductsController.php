<?php

namespace Webkul\Shop\Http\Controllers\API;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\URL;
use Webkul\Product\Repositories\ProductRepository;
use Webkul\Shop\Http\Resources\RamProductResource;

/**
 * RAM Products API Controller
 *
 * Provides product data for Muro Loco integration.
 * Authenticated via service token (ValidateRamServiceToken middleware).
 *
 * @see WI #192, #159
 */
class RamProductsController extends APIController
{
    public function __construct(
        protected ProductRepository $productRepository
    ) {}

    /**
     * Get popular/featured products for Muro Loco.
     *
     * GET /api/ram/products/popular
     *
     * @return JsonResource
     */
    public function popular(Request $request): JsonResource
    {
        // Force public APP_URL for generated URLs (internal Docker requests use different host)
        URL::forceRootUrl(config('app.url'));

        $limit = (int) $request->input('limit', 24);
        $offset = (int) $request->input('offset', 0);

        // Clamp to allowed values (must be in catalog.products.storefront.products_per_page)
        $limit = min(max($limit, 12), 48);
        $offset = max($offset, 0);

        // Convert offset to page number for repository pagination
        $page = (int) floor($offset / $limit) + 1;
        request()->merge(['page' => $page]);

        $products = $this->productRepository->getAll([
            'channel_id'           => core()->getCurrentChannel()->id,
            'status'               => 1,
            'visible_individually' => 1,
            'limit'                => $limit,
            'sort'                 => 'created_at',
            'order'                => 'desc',
        ]);

        return new JsonResource([
            'data' => RamProductResource::collection($products->items()),
            'meta' => [
                'total'  => $products->total(),
                'limit'  => $limit,
                'offset' => $offset,
            ],
        ]);
    }
}
