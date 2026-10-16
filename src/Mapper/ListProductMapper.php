<?php

/**
 * @author Marcin Hubert <>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Spinbits\SyliusBaselinkerPlugin\Mapper;

use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\Product;
use Sylius\Component\Core\Model\ProductVariantInterface;

/**
 * ProductsList describes products, not variants (variants are sent by ProductsData, stock and prices by
 * ProductsQuantity / ProductsPrices), so every product is mapped to a single entry.
 */
class ListProductMapper
{
    private ProductNameResolver $nameResolver;

    public function __construct(ProductNameResolver $nameResolver)
    {
        $this->nameResolver = $nameResolver;
    }

    public function map(Product $product, ChannelInterface $channel): array
    {
        /** @var ProductVariantInterface[] $variants */
        $variants = $product->getEnabledVariants()->toArray();
        /** @var ProductVariantInterface|false $mainVariant */
        $mainVariant = reset($variants);

        return [
            'name' => $this->nameResolver->forProduct($product),
            'quantity' => array_sum(array_map(
                static fn (ProductVariantInterface $variant): int => (int) $variant->getOnHand(),
                $variants
            )),
            'price' => false === $mainVariant ? 0.0 : $this->getPrice($mainVariant, $channel),
            'ean' => null, // not required
            'sku' => $product->getCode(), // not required
            'location' => 'default',
            'currency' => $channel->getBaseCurrency()?->getCode(),
        ];
    }

    private function getPrice(ProductVariantInterface $variant, ChannelInterface $channel): float
    {
        return round(intval($variant->getChannelPricingForChannel($channel)?->getPrice()) / 100, 2);
    }
}
