<?php

namespace Symbio\WebtoolsBundle\Service;

use Symfony\Component\Console\Output\OutputInterface;

abstract class BaseService
{
    protected $logger;

    /**
     * @param OutputInterface $logger
     */
    public function setLogger(OutputInterface $logger) {
        $this->logger = $logger;
    }

    /**
     * Log error message
     *
     * @param $message
     * @param bool $newLine
     */
    protected function logError($message, $newLine = true)
    {
        $this->log($message, true, $newLine);
    }

    /**
     * Log message
     *
     * @param string $message
     * @param boolean $isError Print error message
     * @param boolean $newLine Print new line at the message end
     */
    protected function log($message, $isError = false, $newLine = true) {
        if ($this->logger) {
            switch(get_class($this->logger)) {
                case 'Symfony\Component\Console\Output\BufferedOutput':
                case 'Symfony\Component\Console\Output\ConsoleOutput':
                    $this->logger->{$newLine?'writeln':'write'}($message);
                    break;
                default:
                    if ($isError) error_log($message);
            }
        }
    }
}