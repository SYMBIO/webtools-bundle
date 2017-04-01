<?php

namespace Symbio\WebtoolsBundle\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Loads initial data
 */
class TouchCommand extends ProviderCommand
{
    /**
     * @see Command
     */
    protected function configure()
    {
        $this
            ->setDefinition([])
            ->addArgument('url', InputArgument::REQUIRED, 'Site to crawl')
            ->setName('symbio:webtools:touch')
            ->setDescription('Touch a website')
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

        $provider = $this->getProvider($output);
        $provider->touch($siteUrl, true);
    }
}
