<?php

namespace Symbio\WebtoolsBundle\DependencyInjection;

use Symbio\WebtoolsBundle\Service\Crawler;
use Symbio\WebtoolsBundle\Service\Thumbnailer;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Loader;

/**
 * This is the class that loads and manages your bundle configuration
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html}
 */
class SymbioWebtoolsExtension extends Extension
{
    const ROOT_NAME = 'symbio_webtools';

    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yml');
        $loader->load('parameters.yml');

        $container->setParameter(self::ROOT_NAME . '.' . Crawler::USER_AGENT_PARAM, $config[Crawler::USER_AGENT_PARAM]);
        $container->setParameter(self::ROOT_NAME . '.' . Crawler::EXTRACT_SELECTORS_PARAM, $config[Crawler::EXTRACT_SELECTORS_PARAM]);

        $container->setParameter(self::ROOT_NAME . '.' . Configuration::FACEBOOK_CLIENT_ID_PARAM, $config[Configuration::FACEBOOK_CLIENT_ID_PARAM]);
        $container->setParameter(self::ROOT_NAME . '.' . Configuration::FACEBOOK_CLIENT_SECRET_PARAM, $config[Configuration::FACEBOOK_CLIENT_SECRET_PARAM]);

        $container->setParameter(self::ROOT_NAME . '.' . Thumbnailer::PRIVATE_IPS_PARAM, $config[Thumbnailer::PRIVATE_IPS_PARAM]);
        $container->setParameter(self::ROOT_NAME . '.' . Thumbnailer::BASEDIR_APP_PARAM, $config[Thumbnailer::BASEDIR_APP_PARAM]);
        $container->setParameter(self::ROOT_NAME . '.' . Thumbnailer::BASEDIR_RATIO_PARAM, $config[Thumbnailer::BASEDIR_RATIO_PARAM]);
        $container->setParameter(self::ROOT_NAME . '.' . Thumbnailer::BASEDIR_GRAYSCALE_PARAM, $config[Thumbnailer::BASEDIR_GRAYSCALE_PARAM]);
        $container->setParameter(self::ROOT_NAME . '.' . Thumbnailer::BASEDIRS_PARAM, $config[Thumbnailer::BASEDIRS_PARAM]);
        $container->setParameter(self::ROOT_NAME . '.' . Thumbnailer::SIZE_DIR_PATTERN_PARAM, $config[Thumbnailer::SIZE_DIR_PATTERN_PARAM]);
    }
}