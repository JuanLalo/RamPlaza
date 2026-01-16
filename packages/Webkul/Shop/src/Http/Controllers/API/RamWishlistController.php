<?php

namespace Webkul\Shop\Http\Controllers\API;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Webkul\Customer\Repositories\CustomerRepository;
use Webkul\Customer\Repositories\WishlistRepository;
use Webkul\Product\Repositories\ProductRepository;
use Webkul\SocialLogin\Repositories\CustomerSocialAccountRepository;

/**
 * RAM Wishlist API Controller
 *
 * Provides wishlist (favorites) operations for Muro Loco integration.
 * Authenticated via service token (ValidateRamServiceToken middleware).
 * Identifies users by ram_user_id, not session.
 *
 * @see WI #192
 */
class RamWishlistController extends APIController
{
    public function __construct(
        protected CustomerSocialAccountRepository $socialAccountRepository,
        protected CustomerRepository $customerRepository,
        protected WishlistRepository $wishlistRepository,
        protected ProductRepository $productRepository
    ) {}

    /**
     * Toggle product favorite status.
     *
     * POST /api/ram/wishlist/toggle
     *
     * @return JsonResponse
     */
    public function toggle(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ram_user_id' => 'required|string',
            'product_id'  => 'required|integer|exists:products,id',
            'email'       => 'sometimes|email',
            'first_name'  => 'sometimes|string|max:100',
            'last_name'   => 'sometimes|string|max:100',
        ]);

        $customer = $this->resolveOrCreateCustomer($validated);

        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'No se pudo resolver el cliente',
            ], 400);
        }

        $product = $this->productRepository->findOrFail($validated['product_id']);

        if (!$product->status) {
            return response()->json([
                'success' => false,
                'message' => 'Producto no disponible',
            ], 400);
        }

        $wishlistData = [
            'channel_id'  => core()->getCurrentChannel()->id,
            'product_id'  => $product->id,
            'customer_id' => $customer->id,
        ];

        $existingItem = $this->wishlistRepository->findOneWhere($wishlistData);

        if ($existingItem) {
            $this->wishlistRepository->delete($existingItem->id);

            return response()->json([
                'success'   => true,
                'favorited' => false,
                'message'   => 'Producto eliminado de favoritos',
            ]);
        }

        $this->wishlistRepository->create($wishlistData);

        return response()->json([
            'success'   => true,
            'favorited' => true,
            'message'   => 'Producto agregado a favoritos',
        ]);
    }

    /**
     * Get user's favorited products.
     *
     * GET /api/ram/wishlist
     *
     * Query params:
     * - ram_user_id: required
     * - with_details: optional, if "1" returns full product details
     *
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $ramUserId = $request->input('ram_user_id');

        if (!$ramUserId) {
            return response()->json([
                'success' => false,
                'message' => 'ram_user_id requerido',
            ], 400);
        }

        $customer = $this->resolveCustomer($ramUserId);

        if (!$customer) {
            return response()->json([
                'success'     => true,
                'product_ids' => [],
                'products'    => [],
            ]);
        }

        $wishlistItems = $this->wishlistRepository
            ->where([
                'channel_id'  => core()->getCurrentChannel()->id,
                'customer_id' => $customer->id,
            ])
            ->get();

        $productIds = $wishlistItems->pluck('product_id')->toArray();

        // Return only IDs if details not requested
        if (!$request->input('with_details')) {
            return response()->json([
                'success'     => true,
                'product_ids' => $productIds,
            ]);
        }

        // Return full product details
        // Force public APP_URL for generated URLs (internal Docker requests use different host)
        URL::forceRootUrl(config('app.url'));

        $products = [];
        $publicUrl = rtrim(config('app.ram_public_url') ?? config('app.url'), '/');
        $appUrl = config('app.url');

        foreach ($wishlistItems as $item) {
            $product = $item->product;

            if (!$product || !$product->status) {
                continue;
            }

            $productTypeInstance = $product->getTypeInstance();
            $baseImage = product_image()->getProductBaseImage($product);
            $imageUrl = $baseImage['medium_image_url'] ?? $baseImage['original_image_url'] ?? '';

            // Replace internal URL with public URL
            if ($publicUrl !== rtrim($appUrl, '/') && $imageUrl) {
                $imageUrl = str_replace(rtrim($appUrl, '/'), $publicUrl, $imageUrl);
            }

            $products[] = [
                'id'              => $product->id,
                'name'            => $product->name,
                'price'           => $productTypeInstance->getMinimalPrice(),
                'price_formatted' => core()->formatPrice($productTypeInstance->getMinimalPrice()),
                'image_url'       => $imageUrl,
                'url'             => $publicUrl . '/' . $product->url_key,
            ];
        }

        return response()->json([
            'success'     => true,
            'product_ids' => $productIds,
            'products'    => $products,
        ]);
    }

    /**
     * Resolve customer from ram_user_id.
     */
    protected function resolveCustomer(string $ramUserId): ?object
    {
        $socialAccount = $this->socialAccountRepository->findOneWhere([
            'provider_name' => 'ram',
            'provider_id'   => $ramUserId,
        ]);

        return $socialAccount?->customer;
    }

    /**
     * Resolve customer or create if not found (auto-provision).
     *
     * Follows same pattern as OAuth flow - email is optional.
     * @see CustomerSocialAccountRepository::findOrCreateCustomer
     */
    protected function resolveOrCreateCustomer(array $data): ?object
    {
        $customer = $this->resolveCustomer($data['ram_user_id']);

        if ($customer) {
            return $customer;
        }

        // Auto-provision: email is nullable (same as OAuth flow) #192
        $email = !empty($data['email']) ? $data['email'] : null;

        $customer = $this->customerRepository->create([
            'first_name'        => $data['first_name'] ?? 'Usuario',
            'last_name'         => $data['last_name'] ?? 'RAM',
            'email'             => $email,
            'password'          => bcrypt(bin2hex(random_bytes(16))),
            'channel_id'        => core()->getCurrentChannel()->id,
            'is_verified'       => 1,
            'status'            => 1,
            'customer_group_id' => core()->getConfigData('customer.settings.general.default_group') ?? 1,
        ]);

        $this->socialAccountRepository->create([
            'customer_id'   => $customer->id,
            'provider_name' => 'ram',
            'provider_id'   => $data['ram_user_id'],
        ]);

        return $customer;
    }
}
