<?php

namespace App;

use Monolog\Handler\PsrHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Processor\PsrLogMessageProcessor;
use Psr\Log\LoggerAwareTrait;

trait Logger
{
    use LoggerAwareTrait;
    protected function setLogger() : void
    {
        $logger_name = basename(str_replace('\\', '/', __CLASS__));
        $storage_sub_dir = basename(__DIR__);

        $this->logger = new \Monolog\Logger(
            $logger_name,
            [
                new PsrHandler(app()->make('log'), \Monolog\Logger::WARNING),
                new RotatingFileHandler(storage_path("logs/$storage_sub_dir/$logger_name.log"), 14, \Monolog\Logger::DEBUG, true, 0664),
            ],
            [
                new PsrLogMessageProcessor(),
            ]
        );
    }
}