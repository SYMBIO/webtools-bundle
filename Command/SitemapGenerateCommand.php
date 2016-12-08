<?php

namespace Symbio\WebtoolsBundle\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Loads initial data
 */
class SitemapGenerateCommand extends CrawlerCommand
{
    /**
     * @see Command
     */
    protected function configure()
    {
        $this
            ->setDefinition(array())
            ->addArgument('url', InputArgument::REQUIRED, 'Site to crawl')
            ->addOption('path', null, InputOption::VALUE_OPTIONAL, 'Absolute path to XML from the project root dir', 'web/')
            ->addOption('filename', null, InputOption::VALUE_OPTIONAL, 'Name of XML file', 'sitemap.xml')
            ->setName('symbio:webtools:sitemap')
            ->setDescription('Generate sitemap.xml')
        ;
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $siteUrl = $input->getArgument('url');

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

        $crawler = $this->getCrawler($output);
        $pages = $crawler->extractPages($siteUrl);

        // generate XML
        if ($pages && is_array($pages) && count($pages)) {
            $output->writeln(sprintf('Generate sitemap to "%s"', $sitemapPath));

            $sitemapContent = '<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9 http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd">
';
            foreach($pages as $pageUrl => $pageInfo) {
                $sitemapContent .= sprintf("<url><loc>%s</loc></url>\r\n", $pageUrl);
            }
            $sitemapContent .= '</urlset>';

            // store XML
            file_put_contents($sitemapPath, $sitemapContent);

            $output->writeln(sprintf('Generating finished at %s', date('d.m.Y H:i:s')));
        } else {
            $output->writeln('No pages crawled - sitemap generating failed');
        }
    }}
