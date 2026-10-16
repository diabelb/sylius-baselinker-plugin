<?php

/**
 * @author Jakub Lech <info@smartbyte.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Spinbits\SyliusBaselinkerPlugin\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('spinbits_sylius_baselinker');
        $treeBuilder->getRootNode()
            ->children()
                ->scalarNode('product_name_subtitle_separator')
                    ->info('Separator of a subtitle kept in product names ("Name | Subtitle"); the subtitle is not sent to BaseLinker.')
                    ->defaultNull()
                ->end()
                ->scalarNode('product_url')
                    ->info('Product page URL template with {code}, {slug} and {locale} placeholders. When empty, the sylius_shop_product_show route is used if it exists.')
                    ->defaultNull()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
