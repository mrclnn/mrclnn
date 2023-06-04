<?php

namespace App\Console\Commands\gallery;

use App\Helper;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class db_import extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'gallery:db_import';

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

        //todo здесь должна быть защита от того чтобы не вставить в таблицу в которой уже есть какие-то данные
        $tables = [
            'posts',
            'tags',
            'categories',
            'posts_tags'
        ];

        foreach($tables as $table){

            $fileName = $this->findExportFile($table);
            if(!$fileName) continue;

            $originPath = "{$this->getExportStorage()}/$fileName";
            $targetPath = "{$this->getSQLStorage()}/$fileName";

            if(file_exists($targetPath)) unlink($targetPath);
            copy($originPath, $targetPath);

            //todo мы не получаем никакой обратной связи от скля
            $res = DB::select("LOAD DATA INFILE '$targetPath' INTO TABLE $table");
            echo json_encode($res);
            unlink($targetPath);

            echo "import in $table successfully done\n";

        }

    }

    private function findExportFile(string $table): ?string
    {

        foreach(scandir($this->getExportStorage()) as $exportFile){

            if(
                strpos($exportFile, "export-$table-") === 0 &&
                strpos($exportFile, '.sql') === strlen($exportFile) - 4
            ){
                $foundFiles[] = $exportFile;
            }
        }
        rsort($foundFiles, SORT_STRING);
        return $foundFiles[0] ?? null;

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
