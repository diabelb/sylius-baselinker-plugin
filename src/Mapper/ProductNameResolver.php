<?php

/**
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Spinbits\SyliusBaselinkerPlugin\Mapper;

use Sylius\Component\Core\Model\OrderItemInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;

/**
 * Single place deciding how products are named in BaseLinker (product list, product data and orders).
 *
 * - The variant name is appended only for products with several variants; simple products are named
 *   after the product, since their variant name usually just repeats it.
 * - When a subtitle separator is configured, the part of a name after it ("Name | Subtitle") is dropped.
 */
final class ProductNameResolver
{
    private ?string $subtitleSeparator;

    public function __construct(?string $subtitleSeparator = null)
    {
        $this->subtitleSeparator = '' === $subtitleSeparator ? null : $subtitleSeparator;
    }

    public function forProduct(ProductInterface $product): string
    {
        return $this->stripSubtitle((string) $product->getName());
    }

    public function forVariant(ProductVariantInterface $variant): string
    {
        /** @var ProductInterface|null $product */
        $product = $variant->getProduct();

        return $this->compose(
            (string) $product?->getName(),
            $variant->getName(),
            null === $product || $product->isSimple()
        );
    }

    /** Uses the names stored with the order item, so later catalogue renames do not change placed orders. */
    public function forOrderItem(OrderItemInterface $orderItem): string
    {
        /** @var ProductInterface|null $product */
        $product = $orderItem->getProduct();

        return $this->compose(
            $orderItem->getProductName() ?? (string) $product?->getName(),
            $orderItem->getVariantName() ?? $orderItem->getVariant()?->getName(),
            null === $product || $product->isSimple()
        );
    }

    private function compose(string $productName, ?string $variantName, bool $isSimpleProduct): string
    {
        $productName = $this->stripSubtitle($productName);
        $variantName = $this->stripSubtitle((string) $variantName);

        if ('' === $productName) {
            return $variantName;
        }

        if ($isSimpleProduct || '' === $variantName || $variantName === $productName) {
            return $productName;
        }

        return $productName . ' ' . $variantName;
    }

    private function stripSubtitle(string $name): string
    {
        if (null !== $this->subtitleSeparator) {
            $name = explode($this->subtitleSeparator, $name, 2)[0];
        }

        return trim($name);
    }
}
