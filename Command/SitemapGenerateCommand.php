<?php

namespace Symbio\WebtoolsBundle\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Loads initial data
 */
class SitemapGenerateCommand extends ProviderCommand
{
    /**
     * @see Command
     */
    protected function configure()
    {
        $this
            ->setDefinition([])
            ->addArgument('url', InputArgument::REQUIRED, 'Site to crawl')
            ->addOption('path', null, InputOption::VALUE_OPTIONAL, 'Absolute path to XML from the project root dir', 'web/')
            ->addOption('filename', null, InputOption::VALUE_OPTIONAL, 'Name of XML file', 'sitemap.xml')
            ->addOption('status-codes', null, InputOption::VALUE_NONE, 'Whether print status codes')
            ->setName('symbio:webtools:sitemap')
            ->setDescription('Generate sitemap.xml')
        ;
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $siteUrl = $input->getArgument('url');
        $printStatusCodes = $input->hasOption('status-codes');

        $sitemapPath = $input->getOption('path');
        $sitemapBasename = $input->getOption('filename');

        if ($sitemapPath[0] != '/') {
            $sitemapPath = sprintf(
                '%s/../%s%s%s',
                $this->getContainer()->get('kernel')->getRootDir(),
                $sitemapPath,
                substr($sitemapPath, -1) != '/' ? '/' : '',
                $sitemapBasename
            );
        }

        $provider = $this->getProvider($output);
        $provider->generateSitemap($siteUrl, $sitemapPath, $printStatusCodes);
    }
}
