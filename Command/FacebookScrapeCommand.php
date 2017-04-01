<?php

namespace Symbio\WebtoolsBundle\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Loads initial data
 */
class FacebookScrapeCommand extends ProviderCommand
{
    /**
     * @see Command
     */
    protected function configure()
    {
        $this
            ->setDefinition([])
            ->addArgument('url', InputArgument::REQUIRED, 'Site to crawl')
            ->addOption('status-codes', null, InputOption::VALUE_NONE, 'Whether print status codes')
            ->setName('symbio:webtools:facebook:scrape')
            ->setDescription('Scrape website on Facebook')
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

        $provider = $this->getProvider($output);
        $provider->facebookScrape($siteUrl, $printStatusCodes);
    }
}
