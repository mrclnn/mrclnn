<?php

namespace App\Console\Commands\gallery;

use http\Client\Request;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use pepeEpe\FastImageCompare\ComparatorImageMagick;
use pepeEpe\FastImageCompare\FastImageCompare;
use pepeEpe\FastImageCompare\IComparable;
use pepeEpe\FastImageCompare\NormalizerSquaredSize;

class SearchDuplicates extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'gallery:searchDuplicates';

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
    public function handle(): void
    {

        //todo тупой поворот не воспринимается как отличие

//        $offset = $request->input('offset');
        $offset = 0;

        $posts = DB::table('posts')->orderBy('id', 'desc')->limit(10)->offset($offset)->select('file_name')->get();
//            $posts = DB::table('posts')->orderBy('id', 'desc')->whereBetween('id', [102247, 102256])->limit(10)->select('file_name')->get();
        $posts = array_map(function($post){ return public_path("img/$post->file_name"); }, $posts->toArray());

//            $posts = array_filter(scandir(public_path('img/tmp')), function($file){return strlen($file) > 2;});
//            $posts = array_map(function($post){ return public_path("img/tmp/$post"); }, $posts);
        $enough = 0.15;

        $FIC = new FastImageCompare();
        $imageMagickComparator = new ComparatorImageMagick(ComparatorImageMagick::METRIC_NCC,[]);
        $imageMagickComparator->registerNormalizer(new NormalizerSquaredSize(16));
        $FIC->registerComparator($imageMagickComparator,IComparable::PASSTHROUGH);
        $this->displayImages($posts);
        echo "<H1>FIRST STEP</H1><BR><BR>";
        $duplicates = $FIC->findDuplicates($posts, $enough);
        $this->displayImages($duplicates);

        echo "<H1>SECOND STEP</H1><BR><BR>";
        $uniques = $FIC->findUniques($duplicates, $enough);
        $this->displayImages($uniques);

        echo "<H1>THIRD STEP</H1><BR><BR>";
        $chunks = [];
        foreach ($uniques as $unique){
            $chunk = [$unique];
            foreach ($duplicates as $index => $duplicate) {
                if($FIC->areSimilar($unique, $duplicate, $enough)){
                    $chunk[] = $duplicate;
                    unset($duplicates[$index]);
                }
            }
            if(empty(array_diff($chunk, $uniques))) continue; //todo это значит что в текущем чанке только уникальные элементы, неск штук, а значит это ошибка
            $chunks[] = $chunk;
        }

        foreach($chunks as $index => $chunk){
            echo "<H1>CHUNK $index</H1><BR><BR>";
            $this->displayImages($chunk);
        }
    }
    private function displayImages(array $res)
    {
        $img = array_map(function($path){
            return str_replace('D:\pr\OSPanel\domains\mrclnn\public_html\\', '', "<img style='width: 20%' src='$path'>");
        }, $res);
        echo implode('', $img);
    }
}
