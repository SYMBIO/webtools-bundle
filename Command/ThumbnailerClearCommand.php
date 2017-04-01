<?php

namespace Symbio\WebtoolsBundle\Command;

use Symbio\WebtoolsBundle\Exception\ThumbnailerException;
use Symbio\WebtoolsBundle\Exception\ThumbnailerNotFoundException;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Loads initial data
 */
class ThumbnailerClearCommand extends ContainerAwareCommand
{
    /**
     * @see Command
     */
    protected function configure()
    {
        $this
            ->setDefinition([])
            ->addOption('basedir', null,InputOption::VALUE_OPTIONAL, 'Basedir to clear')
            ->setName('symbio:webtools:thumbnailer:clear')
            ->setDescription('Clear generated thumbnails')
        ;
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $baseDir = $input->getOption('basedir');

        $thumbnailer = $this->getContainer()->get('symbio_webtools.thumbnailer');
        $baseDirs = !$baseDir ? $this->getContainer()->getParameter('symbio_webtools.thumbnailer_basedirs') : [$baseDir];

        $messages = [];

        foreach($baseDirs as $baseDir) {
            try {
                $output->writeln(sprintf('Deleting directory "%s":', $baseDir));
                $thumbnailer->clearAll($baseDir, $output);
            } catch (ThumbnailerNotFoundException $e) {
                // directory doesn't exists - do nothing
            } catch (ThumbnailerException $e) {
                $output->writeln($e->getMessage());
            }
        }
    }
}