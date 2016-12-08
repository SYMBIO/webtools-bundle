<?php

namespace Symbio\WebtoolsBundle\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

/**
 * Loads initial data
 */
class TouchCommand extends CreateIndexCommand
{
    /**
     * @see Command
     */
    protected function configure()
    {
        $this
            ->setDefinition(array())
            ->addArgument('url', InputArgument::REQUIRED, 'Site to crawl')
            ->addOption('depth', null, InputOption::VALUE_OPTIONAL, 'The depth to crawl to (default is all)', false)
            ->setName('symbio:webtools:touch')
            ->setDescription('Touch a website')
        ;
        $this->defaultParameters['dont-clean'] = true;
        $this->defaultParameters['indexing'] = false;
    }
}
