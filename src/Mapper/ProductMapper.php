<?php

/**
 * @author Marcin Hubert <>
 * @author Jakub Lech <info@smartbyte.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Spinbits\SyliusBaselinkerPlugin\Mapper;

use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Sylius\Component\Attribute\Model\AttributeInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ImageInterface;
use Sylius\Component\Core\Model\Product;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Core\Model\TaxonInterface;
use Sylius\Component\Locale\Model\LocaleInterface;
use Sylius\Component\Taxation\Resolver\TaxRateResolverInterface;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\RouterInterface;

class ProductMapper
{
    private CacheManager $cacheManager;
    private TaxRateResolverInterface $taxRateResolver;
    private RouterInterface $router;
    private ProductNameResolver $nameResolver;
    private ?string $productUrlTemplate;

    public function __construct(
        CacheManager $cacheManager,
        TaxRateResolverInterface $taxRateResolver,
        RouterInterface $router,
        ProductNameResolver $nameResolver,
        ?string $productUrlTemplate = null
    ) {
        $this->cacheManager = $cacheManager;
        $this->taxRateResolver = $taxRateResolver;
        $this->router = $router;
        $this->nameResolver = $nameResolver;
        $this->productUrlTemplate = '' === $productUrlTemplate ? null : $productUrlTemplate;
    }

    public function map(Product $product, ChannelInterface $channel): array
    {
        /** @var ProductVariantInterface[] $variants */
        $variants = $product->getEnabledVariants()->toArray();
        /** @var ProductVariantInterface|false $mainVariant */
        $mainVariant = reset($variants);

        return [
            'sku' => $product->getCode(),
            'name' => $this->nameResolver->forProduct($product),
            'tax' => false === $mainVariant ? 0 : $this->getTax($mainVariant, $channel),
            'quantity' => false === $mainVariant ? 0 : $mainVariant->getOnHand(),
            'price' => false === $mainVariant ? 0.0 : $this->getPrice($mainVariant, $channel),
            'description' => $product->getDescription(),
            'categoryId' => $this->getTaxon($product),
            'images' => $this->getImages($product),
            'variants' => $this->getVariants($variants, $channel),
            'features' => $this->getFeatures($product),
            'allCategories' => $this->getTaxonomies($product),
            'allCategoriesExpanded' => $this->getTaxonomiesExpanded($product),
            'shortDescription' => $product->getShortDescription(),
            'slug' => $product->getSlug(),
            'url' => $this->getUrl($product, $channel),
        ];
    }

    private function getUrl(Product $product, ChannelInterface $channel): string
    {
        /** @var LocaleInterface|null $defaultLocale */
        $defaultLocale = $channel->getDefaultLocale();
        $locale = (string) $defaultLocale?->getCode();

        if (null !== $this->productUrlTemplate) {
            return strtr($this->productUrlTemplate, [
                '{code}' => rawurlencode((string) $product->getCode()),
                '{slug}' => rawurlencode((string) $product->getSlug()),
                '{locale}' => rawurlencode($locale),
            ]);
        }

        // Headless shops have no Sylius shop routes; the URL is optional for BaseLinker.
        try {
            return $this->router->generate('sylius_shop_product_show', [
                '_locale' => $locale,
                'slug' => $product->getSlug(),
            ], RouterInterface::ABSOLUTE_URL);
        } catch (RouteNotFoundException $exception) {
            return '';
        }
    }

    private function getTax(ProductVariantInterface $variant, ChannelInterface $channel): int
    {
        $criteria = ['zone' => $channel->getDefaultTaxZone()];
        $taxRate = $this->taxRateResolver->resolve($variant, $criteria);

        return intval($taxRate?->getAmount() * 100);
    }

    private function getTaxon(Product $product): string
    {
        /** @var TaxonInterface|null $mainTaxon */
        $mainTaxon = $product->getMainTaxon();
        if ($mainTaxon !== null) {
            return (string) $mainTaxon->getCode();
        }

        return '';
    }

    private function getImages(Product $product): array
    {
        $cache = $this->cacheManager;
        return $product->getImages()->map(function (ImageInterface $image) use ($cache): string {
            return $cache->getBrowserPath((string) parse_url((string) $image->getPath(), PHP_URL_PATH), 'sylius_admin_product_original');
        })->toArray();
    }

    private function getTaxonomies(Product $product): array
    {
        return $product->getTaxons()->map(function (TaxonInterface $taxon): string {
            return (string) $taxon->getName();
        })->toArray();
    }

    private function getTaxonomiesExpanded(Product $product): array
    {
        return $product->getTaxons()->map(function (TaxonInterface $taxon): string {
            return (string) $taxon->getFullname();
        })->toArray();
    }

    /** @param ProductVariantInterface[] $variants */
    private function getVariants(array $variants, ChannelInterface $channel): array
    {
        $return = [];
        foreach ($variants as $variant) {
            $quantity = (int) $variant->getOnHand() - (int) $variant->getOnHold();
            $name = $this->nameResolver->forVariant($variant);
            $return[(int) $variant->getId()] = [
                'full_name' => $name,
                'name' => $name,
                'price' => $this->getPrice($variant, $channel),
                'quantity' => $quantity,
                'sku' => $variant->getCode(),
            ];
        }
        return $return;
    }

    private function getFeatures(Product $product): array
    {
        $return = [];
        foreach ($product->getAttributes() as $attribute) {
            $name = '';
            /** @var AttributeInterface|null $attr */
            $attr = $attribute->getAttribute();
            if ($attr !== null) {
                /** @var AttributeInterface $attr */
                $name = $attr->getName();
            }
            $return[(string) $attribute->getCode()] = [$name, $attribute->getValue()];
        }
        return array_values($return);
    }

    private function getPrice(ProductVariantInterface $variant, ChannelInterface $channel): float
    {
        return round(intval($variant->getChannelPricingForChannel($channel)?->getPrice()) / 100, 2);
    }
}
