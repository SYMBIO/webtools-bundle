<?php

namespace Symbio\WebtoolsBundle\DependencyInjection;

use Symbio\WebtoolsBundle\Service\Crawler;
use Symbio\WebtoolsBundle\Service\Thumbnailer;
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

    const FACEBOOK_CLIENT_ID_PARAM = 'facebook_client_id';
    const FACEBOOK_CLIENT_SECRET_PARAM = 'facebook_client_secret';

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
                // selectors to extract URLs
                ->arrayNode(Crawler::EXTRACT_SELECTORS_PARAM)
                    ->beforeNormalization()
                        ->ifString()
                        ->then(function($v) { return preg_split('/\s*,\s*/', $v); })
                    ->end()
                    ->canBeUnset()
                    ->defaultValue([
                        'html/body//a[not(@rel="nofollow")]'
                    ])
                    ->requiresAtLeastOneElement()
                    ->prototype('scalar')->end()
                ->end()
                // facebook app
                ->scalarNode(self::FACEBOOK_CLIENT_ID_PARAM)->defaultValue('135189216968450')->end()
                ->scalarNode(self::FACEBOOK_CLIENT_SECRET_PARAM)->defaultValue('7920c0b65de3a2e3582468a28c3841b9')->end()
                // thumbnailer private IPs
                ->arrayNode(Thumbnailer::PRIVATE_IPS_PARAM)
                    ->beforeNormalization()
                        ->ifString()
                        ->then(function($v) { return preg_split('/\s*,\s*/', $v); })
                    ->end()
                    ->canBeUnset()
                    ->defaultValue([])
                    ->requiresAtLeastOneElement()
                    ->prototype('scalar')->end()
                ->end()
                // thumbnailer basedirs
                ->arrayNode(Thumbnailer::BASEDIRS_PARAM)
                    ->beforeNormalization()
                        ->ifString()
                        ->then(function($v) { return preg_split('/\s*,\s*/', $v); })
                    ->end()
                    ->canBeUnset()
                    ->defaultValue([
                        't',
                        'tr',
                        'tg',
                        'ta',
                    ])
                    ->requiresAtLeastOneElement()
                    ->prototype('scalar')->end()
                ->end()
                // thumbnailer base dirs
                ->scalarNode(Thumbnailer::BASEDIR_RATIO_PARAM)->defaultValue('tr')->end()
                ->scalarNode(Thumbnailer::BASEDIR_GRAYSCALE_PARAM)->defaultValue('tg')->end()
                ->scalarNode(Thumbnailer::BASEDIR_APP_PARAM)->defaultValue('ta')->end()
                // thumbnailer directory-size-name pattern
                ->scalarNode(Thumbnailer::SIZE_DIR_PATTERN_PARAM)->defaultValue('/w(\d+)h(\d+)/')->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
