<?php

namespace App\Console\Commands;

use App\Logger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class testCommand extends Command
{
    use Logger;
    protected $signature = 'command:test {action?}';
    public function __construct()
    {
        parent::__construct();
    }
    public function handle(): void
    {

        $res = Redis::get('key');
        dump($res);
        $res = Redis::set('key', 'value');
        dump($res);
        $res = Redis::get('key');
        dump($res);

//        $this->setLogger();
//        $action = $this->argument('action');
//        dump($action);
//        $this->logger->info('hello from test command');
    }
}