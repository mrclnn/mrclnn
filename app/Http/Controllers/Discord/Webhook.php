<?php

namespace App\Http\Controllers\Discord;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Monolog\Handler\PsrHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;
use Monolog\Processor\PsrLogMessageProcessor;
use Psr\Log\LoggerAwareTrait;

class Webhook extends Controller
{
    public function __construct()
    {
        $this->setLogger();
        $this->logger->debug('from constructor');
        set_error_handler(function($level, $message, $file, $line, $context){
            $this->logger->error("Error $level: $message occurred in file $file at line $line. context: ".json_encode($context));
        });
    }

    public function execute()
    {
        $this->logger->info('received discord request');
        DB::table('discord_webhook_log')->insert([
            'get_data' => json_encode($_GET),
            'post_data' => json_encode($_GET),
            'input_data' => file_get_contents('php://input'),
        ]);
    }

    use LoggerAwareTrait;
    private function setLogger() : void
    {
        $logger_name = basename(str_replace('\\', '/', __CLASS__));
        $storage_sub_dir = basename(__DIR__);

        $this->logger = new Logger(
            $logger_name,
            [
                new PsrHandler(app()->make('log'), Logger::WARNING),
                new RotatingFileHandler(storage_path("logs/$storage_sub_dir/$logger_name.log"), 14, Logger::DEBUG, true, 0664),
            ],
            [
                new PsrLogMessageProcessor(),
            ]
        );
    }
}