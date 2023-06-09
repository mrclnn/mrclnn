<?php

namespace App\Console\Commands\gallery;

use App\Helper;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class db_export extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'gallery:db_export {table=null}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {

        $tables = [
            'posts',
            'tags',
            'categories',
            'posts_tags'
        ];

        foreach($tables as $table){

            $filename = $this->getExportFileName($table);

            $originPath = "{$this->getSQLStorage()}/$filename";
            $targetPath = "{$this->getExportStorage()}/$filename";

            if(file_exists($originPath)) unlink($originPath);

            //todo мы не получаем никакой обратной связи от скля
            DB::select("SELECT * INTO OUTFILE '$originPath' FROM $table");

            if($originPath !== $targetPath) rename($originPath, $targetPath);

            echo "Export file for $table successfully created\n";
        }

    }

    private function getExportFileName(string $table): string
    {
        return "export-$table-" . Carbon::now()->format('Y-m-d') . '.sql';
    }

    private function getExportStorage(): string
    {
        $exportFolder = storage_path('backup/gallery/database');
        if(!file_exists($exportFolder)) mkdir($exportFolder, 0777, true);
        return Helper::clearDirPath($exportFolder);
    }

    private function getSQLStorage(): string
    {
        $SQLStorage = DB::select('show variables like "secure_file_priv"')[0]->Value ?? $this->getExportStorage();
        return Helper::clearDirPath($SQLStorage);
    }
}
