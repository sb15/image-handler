<?php

namespace Sb\ImageHandler\Logger;

use Psr\Log\AbstractLogger;

class FileLogger extends AbstractLogger
{
    /** @var string */
    private $filename;

    public function __construct(string $filename)
    {
        $this->filename = $filename;
    }

    /**
     * @param mixed $level
     * @param string $message
     * @param array<int, string> $context
     * @throws \Exception
     */
    public function log($level, $message, array $context = array()): void
    {
        $line = '[' . (new \DateTime())->format(DATE_RFC3339) . '] ' .
            $level . ': ' . $message;

        if ($context) {
            $line .= json_encode($context);
        }

        file_put_contents($this->filename, $line . "\n", FILE_APPEND);
    }
}