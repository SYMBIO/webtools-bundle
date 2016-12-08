<?php

namespace Symbio\WebtoolsBundle\Command;

use Symbio\WebtoolsBundle\Service\Crawler;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Output\OutputInterface;

abstract class CrawlerCommand extends ContainerAwareCommand
{
    /**
     * @return Crawler
     */
    protected function getCrawler(OutputInterface $output = null) {
        $crawler = $this->getContainer()->get('symbio_webtools.crawler');
        if ($output) {
            $crawler->setLogger($output);
        }
        return $crawler;
    }
}