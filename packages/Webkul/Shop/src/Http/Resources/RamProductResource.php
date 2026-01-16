<?php

namespace Webkul\Shop\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Simplified product resource for RAM integration.
 *
 * Returns only fields needed by Muro Loco product cards.
 * Uses RAM_PUBLIC_URL for browser-accessible URLs.
 *
 * @see WI #192
 */
class RamProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param \Illuminate\Http\Request $request
     * @return array
     */
    public function toArray($request): array
    {
        $productTypeInstance = $this->getTypeInstance();
        $baseImage = product_image()->getProductBaseImage($this);
        $publicUrl = $this->getPublicBaseUrl();

        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'description' => $this->short_description ?? mb_substr(strip_tags($this->description ?? ''), 0, 100),
            'price'       => $productTypeInstance->getMinimalPrice(),
            'price_formatted' => core()->formatPrice($productTypeInstance->getMinimalPrice()),
            'image_url'   => $this->makePublicUrl($baseImage['medium_image_url'] ?? $baseImage['original_image_url'] ?? '', $publicUrl),
            'url'         => $publicUrl . '/' . $this->url_key,
            'is_saleable' => (bool) $productTypeInstance->isSaleable(),
            'on_sale'     => (bool) $productTypeInstance->haveDiscount(),
        ];
    }

    /**
     * Get public base URL for user-facing links.
     */
    private function getPublicBaseUrl(): string
    {
        return rtrim(config('app.ram_public_url') ?? config('app.url'), '/');
    }

    /**
     * Replace internal URL with public URL.
     */
    private function makePublicUrl(string $url, string $publicUrl): string
    {
        if (empty($url)) {
            return '';
        }

        $appUrl = config('app.url');

        return str_replace(rtrim($appUrl, '/'), $publicUrl, $url);
    }
}
