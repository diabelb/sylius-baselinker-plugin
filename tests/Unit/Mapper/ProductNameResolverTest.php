<?php

/**
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tests\Spinbits\SyliusBaselinkerPlugin\Unit\Mapper;

use PHPUnit\Framework\TestCase;
use Spinbits\SyliusBaselinkerPlugin\Mapper\ProductNameResolver;
use Sylius\Component\Core\Model\OrderItemInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;

class ProductNameResolverTest extends TestCase
{
    public function testSimpleProductIsNamedAfterProductOnly(): void
    {
        $sut = new ProductNameResolver();
        $variant = $this->variant('Sok Jabłkowy 3L', $this->product('Sok Jabłkowy 3L', true));

        $this->assertSame('Sok Jabłkowy 3L', $sut->forVariant($variant));
    }

    public function testVariantNameIsAppendedForProductsWithManyVariants(): void
    {
        $sut = new ProductNameResolver();
        $variant = $this->variant('5 kg', $this->product('Jabłka Ligol', false));

        $this->assertSame('Jabłka Ligol 5 kg', $sut->forVariant($variant));
    }

    public function testVariantNameRepeatingProductNameIsNotAppended(): void
    {
        $sut = new ProductNameResolver();
        $variant = $this->variant('Jabłka Ligol', $this->product('Jabłka Ligol', false));

        $this->assertSame('Jabłka Ligol', $sut->forVariant($variant));
    }

    public function testSubtitleIsDroppedWhenSeparatorIsConfigured(): void
    {
        $sut = new ProductNameResolver('|');
        $product = $this->product('Sok Jabłkowy 100% 3L | Naturalny Sok Tłoczony', true);

        $this->assertSame('Sok Jabłkowy 100% 3L', $sut->forProduct($product));
        $this->assertSame(
            'Sok Jabłkowy 100% 3L',
            $sut->forVariant($this->variant('Sok Jabłkowy 100% 3L | Naturalny Sok Tłoczony', $product))
        );
    }

    public function testSubtitleIsKeptWithoutSeparator(): void
    {
        $sut = new ProductNameResolver('');

        $this->assertSame('Sok | Tłoczony', $sut->forProduct($this->product('Sok | Tłoczony', true)));
    }

    public function testOrderItemUsesNamesStoredWithTheOrder(): void
    {
        $sut = new ProductNameResolver('|');
        $orderItem = $this->createMock(OrderItemInterface::class);
        $orderItem->method('getProduct')->willReturn($this->product('Sok Jabłko Marchew 3L (nowa nazwa)', true));
        $orderItem->method('getProductName')->willReturn('Sok Jabłko Marchew 3L | Naturalny Sok Tłoczony');
        $orderItem->method('getVariantName')->willReturn('Sok jabłkowy z dodatkiem MARCHWI 3L | Naturalny Sok Tłoczony');

        $this->assertSame('Sok Jabłko Marchew 3L', $sut->forOrderItem($orderItem));
    }

    public function testOrderItemWithoutStoredProductNameFallsBackToVariantName(): void
    {
        $sut = new ProductNameResolver('|');
        $orderItem = $this->createMock(OrderItemInterface::class);
        $orderItem->method('getProduct')->willReturn(null);
        $orderItem->method('getProductName')->willReturn(null);
        $orderItem->method('getVariantName')->willReturn('Miód Malina 430g');

        $this->assertSame('Miód Malina 430g', $sut->forOrderItem($orderItem));
    }

    private function product(string $name, bool $isSimple): ProductInterface
    {
        $product = $this->createMock(ProductInterface::class);
        $product->method('getName')->willReturn($name);
        $product->method('isSimple')->willReturn($isSimple);

        return $product;
    }

    private function variant(?string $name, ProductInterface $product): ProductVariantInterface
    {
        $variant = $this->createMock(ProductVariantInterface::class);
        $variant->method('getName')->willReturn($name);
        $variant->method('getProduct')->willReturn($product);

        return $variant;
    }
}
