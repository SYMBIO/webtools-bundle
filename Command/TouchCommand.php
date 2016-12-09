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
            ->setDefinition(array())
            ->addArgument('url', InputArgument::REQUIRED, 'Site to crawl')
            ->setName('symbio:webtools:touch')
            ->setDescription('Touch a website')
        ;
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $siteUrl = $input->getArgument('url');

        $provider = $this->getProvider($output);
        $provider->touch($siteUrl, true);
    }
}
