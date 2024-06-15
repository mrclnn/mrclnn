<?php

namespace App\Http\Controllers;

use Google\Client;
use Illuminate\Http\Request;
use Monolog\Handler\PsrHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;
use Monolog\Processor\PsrLogMessageProcessor;
use Psr\Log\LoggerAwareTrait;

class GoogleApiController extends Controller
{

    public function __construct()
    {
        $this->setLogger();
    }

    public function execute(Request $request)
    {
        try{
            /************************************************
             * If we have a code back from the OAuth 2.0 flow,
             * we need to exchange that with the
             * Google\Client::fetchAccessTokenWithAuthCode()
             * function. We store the resultant access token
             * bundle in the session, and redirect to ourself.
             ************************************************/
            if (isset($_GET['code'])) {
                $client = new Client();
                $client->setAuthConfig(storage_path('credit/').env('GOOGLE_API_CREDIT'));
                $code_verifier = $client->getOAuth2Service()->generateCodeVerifier();
                $this->logger->info($code_verifier);
                $token = $client->fetchAccessTokenWithAuthCode($_GET['code'], $code_verifier);
                $this->logger->info("token : ", $token);

                $client->setAccessToken($token);

                // redirect back to the example
                header('Location: /dbg');
            }
        } catch (\Throwable $e){
            $this->logger->error("{$e->getMessage()} in file {$e->getFile()} at line {$e->getLine()}");
        }

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