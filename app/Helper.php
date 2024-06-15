<?php


namespace App;


use App\Models\Categories;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Psr\Log\LoggerInterface;
use Illuminate\Database\Eloquent\Model;

class Helper
{

    public static function sendPost(int $post_id){
        $path = DB::table('posts')->where('post_id', $post_id)->first()->file_name ?? null;
        if(!$path){
            self::log('not found');
            return;
        }
        try{
            $path = Storage::disk('gallery_posts')->exists($path) ? Storage::disk('gallery_posts')->path($path) : public_path('img/no-file.png');
            $token = env('TELEGRAM_API_KEY_HENTAI');
            $arrayQuery = array(
                'chat_id' => env('TELEGRAM_MAIN_CHAT_ID'),
                'caption' => '',
                'photo' => curl_file_create($path, 'image/jpg' , 'mew post name.jpg')
            );
            $ch = curl_init('https://api.telegram.org/bot'. $token .'/sendPhoto');
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $arrayQuery);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HEADER, false);
            $res = curl_exec($ch);
            curl_close($ch);
        } catch (\Throwable $e){
            self::log("{$e->getMessage()} in file {$e->getFile()} at line {$e->getLine()}");
        }

    }

    public static function sendMediaGroup(array $post_ids)
    {
        if(empty($post_ids)){
            self::log('empty ids array');
            return;
        }
        $path_list = DB::table('posts')->whereIn('post_id', $post_ids)->get()->pluck('file_name')->all();
        if(empty($path_list)){
            self::log('not found');
            return;
        }
        try{
            $path_list = array_map(function($path){
                return Storage::disk('gallery_posts')->exists($path) ? Storage::disk('gallery_posts')->path($path) : public_path('img/no-file.png');
            }, $path_list);
            $curl_photos = array_map(function($path){
                return curl_file_create($path, 'image/jpg' , 'mew post name.jpg');
            }, $path_list);
            $token = env('TELEGRAM_API_KEY_HENTAI');
            $arrayQuery = array(
                'chat_id' => env('TELEGRAM_MAIN_CHAT_ID'),
                'media' => json_encode(collect($curl_photos)->map(function($curl_photo){
                    return [
                        'type' => 'photo',
                        'media' => "attach://$curl_photo->name",
                    ];
                })->all()),
            );
            foreach($curl_photos as $curl_photo){
                $arrayQuery[$curl_photo->name] = $curl_photo;
            }
//            self::log(json_encode($arrayQuery));
            $ch = curl_init('https://api.telegram.org/bot'. $token .'/sendMediaGroup');
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $arrayQuery);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HEADER, false);
            $res = curl_exec($ch);
            curl_close($ch);
        } catch (\Throwable $e){
            self::log("{$e->getMessage()} in file {$e->getFile()} at line {$e->getLine()}");
        }
    }
    public static function log(string $message, $context = []) : void
    {
        (new self)->sendToTG($message);
    }
    public static function getFieldFromModel(Collection $models, string $field): array
    {
        return $models->map(function (Model $model) use ($field) {
            return $model->$field;
        })->toArray();
    }
    // убирает с конца адреса папки слэш, заменяет бэкслеши на обычные слэши
    public static function clearDirPath(string $path): string
    {
        return preg_replace(['/\\\\/', '/\/$/'], ['/', ''], $path);
    }
    public static function isIntListString(string $string): bool
    { // true если строка содержит только цифры разделённые запятой
        return preg_match('/^[\d,]+$/', trim($string)) === 1;
    }
    public static function convertToIntListArray(array $array)
    {

    }
    public static function isIntListArray(array &$array, bool $convertStringToInt = false): bool
    {
        // строка содержащая только цифры будет трактоваться как int
        $imploded = implode(',', $array);
        // такой подход сможет определить наличие в массиве элемента по типу '5,754,346,34',
        // который без проверки интерпретировался бы как 4 разных элемента
        if(count(explode(',', $imploded)) !== count($array)) return false;
        if(!self::isIntListString($imploded)) return false;

        if($convertStringToInt){
            foreach ($array as &$item){
                if(is_string($item)) $item = (int)$item;
            }
        }
        return true;
    }
    public static function isIntListArrayStrict(array $array): bool
    {
        $notIntegerItems = array_filter($array, function($item){ return !is_int($item); });
        return empty($notIntegerItems);
    }

    public static function test(LoggerInterface $logger = null)
    {
        $category = Categories::getFromName('All');
        for($i = 10; $i > 0; $i--){
            $begin0 = microtime(true);

            $category->getPostsForSlider(40, 0.7);

            if($logger){
                $logger->debug('FROM HELPER getting posts time: '. (microtime(true) - $begin0));
            } else {
                echo 'FROM HELPER getting posts time: '. (microtime(true) - $begin0) .'<br>';
            }

        }

    }
    public function sendToTG(string $message){
        $ch = curl_init();
        curl_setopt_array(
            $ch,
            array(
                CURLOPT_URL => 'https://api.telegram.org/bot'.env('TELEGRAM_API_KEY_HENTAI').'/sendMessage',
                CURLOPT_POST => TRUE,
                CURLOPT_RETURNTRANSFER => TRUE,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_POSTFIELDS => array(
                    'chat_id' => env('TELEGRAM_MAIN_CHAT_ID'),
                    'text' => $message,
                ),
            )
        );
        curl_exec($ch);
    }

    public static function sendRandMediaGroup($quantity)
    {
        $quantity = min(200, (int)$quantity);
        $path = DB::table('posts')->where('status', 2)->orderByRaw("RAND()")->limit($quantity)->get()->pluck('post_id')->all() ?? [];
        foreach(array_chunk($path, 10) as $chunk){
            self::sendMediaGroup($chunk);
        }
    }

    public static function sendMediaGroupByIds(Collection $ids)
    {
        $quantity = min(200, count($ids));
        $path = DB::table('posts')->whereIn('id', $ids)->orderByRaw("RAND()")->limit($quantity)->get()->pluck('post_id')->all() ?? [];
        foreach(array_chunk($path, 10) as $chunk){
            self::sendMediaGroup($chunk);
        }
    }
    public static function sendRandPost()
    {
        $path = DB::table('posts')->where('status', 2)->orderByRaw("RAND()")->first()->file_name ?? null;
        if(!$path){
            self::log('not found');
            return;
        }
        try{
            $path = Storage::disk('gallery_posts')->exists($path) ? Storage::disk('gallery_posts')->path($path) : public_path('img/no-file.png');
            $token = env('TELEGRAM_API_KEY_HENTAI');
            $arrayQuery = array(
                'chat_id' => env('TELEGRAM_MAIN_CHAT_ID'),
                'caption' => '',
                'photo' => curl_file_create($path, 'image/jpg' , 'mew post name.jpg')
            );
            $ch = curl_init('https://api.telegram.org/bot'. $token .'/sendPhoto');
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $arrayQuery);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HEADER, false);
            $res = curl_exec($ch);
            curl_close($ch);
        } catch (\Throwable $e){
            self::log("{$e->getMessage()} in file {$e->getFile()} at line {$e->getLine()}");
        }
    }
}