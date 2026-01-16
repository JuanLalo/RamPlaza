<?php

namespace Webkul\Shop\Http\Controllers\API;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Webkul\Checkout\Facades\Cart;
use Webkul\Checkout\Repositories\CartRepository;
use Webkul\Customer\Repositories\CustomerRepository;
use Webkul\Product\Repositories\ProductRepository;
use Webkul\SocialLogin\Repositories\CustomerSocialAccountRepository;

/**
 * RAM Cart API Controller
 *
 * Provides cart operations for Muro Loco integration.
 * Authenticated via service token (ValidateRamServiceToken middleware).
 * Identifies users by ram_user_id, not session.
 *
 * @see WI #192, #159
 */
class RamCartController extends APIController
{
    public function __construct(
        protected CustomerSocialAccountRepository $socialAccountRepository,
        protected CustomerRepository $customerRepository,
        protected ProductRepository $productRepository,
        protected CartRepository $cartRepository
    ) {}

    /**
     * Add product to cart.
     *
     * POST /api/ram/cart/add
     *
     * @return JsonResponse
     */
    public function add(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ram_user_id' => 'required|string',
            'product_id'  => 'required|integer|exists:products,id',
            'quantity'    => 'integer|min:1',
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

        // Initialize cart for customer (stateless API - no auth guard)
        Cart::initCart($customer);

        // Create cart if doesn't exist (with explicit customer)
        if (!Cart::getCart()) {
            Cart::createCart(['customer' => $customer]);
        }

        try {
            $cart = Cart::addProduct($product, [
                'product_id' => $product->id,
                'quantity'   => $validated['quantity'] ?? 1,
            ]);

            if ($cart instanceof \Exception) {
                throw $cart;
            }

            return response()->json([
                'success'    => true,
                'cart_count' => $this->getCartItemCount($customer),
                'message'    => 'Producto agregado al carrito',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get cart item count.
     *
     * GET /api/ram/cart/count
     *
     * @return JsonResponse
     */
    public function count(Request $request): JsonResponse
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
            return response()->json(['count' => 0]);
        }

        return response()->json([
            'count' => $this->getCartItemCount($customer),
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
     * Resolve customer or create if auto-provision data provided.
     */
    protected function resolveOrCreateCustomer(array $data): ?object
    {
        $customer = $this->resolveCustomer($data['ram_user_id']);

        if ($customer) {
            return $customer;
        }

        if (empty($data['email'])) {
            return null;
        }

        $customer = $this->customerRepository->create([
            'first_name'        => $data['first_name'] ?? 'Usuario',
            'last_name'         => $data['last_name'] ?? 'RAM',
            'email'             => $data['email'],
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

    /**
     * Get item count from customer's active cart.
     */
    protected function getCartItemCount(object $customer): int
    {
        $cart = $this->cartRepository->findOneWhere([
            'customer_id' => $customer->id,
            'is_active'   => 1,
        ]);

        if (!$cart) {
            return 0;
        }

        return $cart->items->sum('quantity');
    }
}
