<?php

namespace Symbio\WebtoolsBundle\Command;

use Symbio\WebtoolsBundle\Service\Provider;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Output\OutputInterface;

abstract class ProviderCommand extends ContainerAwareCommand
{
    /**
     * @return Provider
     */
    protected function getProvider(OutputInterface $output = null) {
        $provider = $this->getContainer()->get('symbio_webtools.provider');
        if ($output) {
            $provider->setLogger($output);
        }
        return $provider;
    }
}