<?php

namespace App\Console\Commands;

use App\Logger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

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
        $this->setLogger();
        $action = $this->argument('action');
        dump($action);
        $this->logger->info('hello from test command');
    }
}