<?php

namespace App\lib\AmoHelper;

use App\lib\FORMAT;
use Monolog\Handler\PsrHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Processor\PsrLogMessageProcessor;
use Psr\Log\LoggerAwareTrait;

trait Logger
{
    use LoggerAwareTrait;
    private function setLogger() : void
    {
        $path = FORMAT::getPath(__CLASS__);
        $logger_name = basename($path);
        $storage_sub_dir = basename(str_replace($logger_name, '', $path));

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