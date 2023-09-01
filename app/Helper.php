<?php


namespace App;


use App\Models\Categories;
use Illuminate\Support\Collection;
use Psr\Log\LoggerInterface;
use Illuminate\Database\Eloquent\Model;

class Helper
{

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
                CURLOPT_URL => 'https://api.telegram.org/bot1870702904:AAFEsvY_Gy0E6lSrJTR3exGv2xWRJkyAZjQ/sendMessage',
                CURLOPT_POST => TRUE,
                CURLOPT_RETURNTRANSFER => TRUE,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_POSTFIELDS => array(
                    'chat_id' => '438500729',
                    'text' => $message,
                ),
            )
        );
        curl_exec($ch);
    }
}