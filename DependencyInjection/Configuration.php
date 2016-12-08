<?php

namespace Symbio\WebtoolsBundle\DependencyInjection;

use Symbio\WebtoolsBundle\Service\Crawler;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * This is the class that validates and merges configuration from your app/config files
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html#cookbook-bundles-extension-config-class}
 */
class Configuration implements ConfigurationInterface
{
    const USER_AGENT = 'Webtools search crawler by SYMBIO';

    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root(SymbioWebtoolsExtension::ROOT_NAME);

        $rootNode
            ->children()
                // crawler user agent info
                ->scalarNode(Crawler::USER_AGENT_PARAM)->defaultValue(self::USER_AGENT)->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
