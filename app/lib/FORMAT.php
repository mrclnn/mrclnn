<?php

namespace App\lib;

use DateTime;
use DateTimeZone;
use Generator;
use Illuminate\Support\Collection;

class FORMAT
{
    const MALE = 'male';
    const FEMALE = 'female';
    /**
     * Принимает $date строку, которая может содержать или timestamp или корректное значение для strtotime
     * Возвращает DateTime или null соответствующие переданному значению
     *
     * @param string|null $date
     * @param string|null $timezone
     * @return DateTime|null
     */
    public static function DateTime(?string $date, ?string $timezone = null): ?DateTime
    {
        if(!$date) return null;
        try{
            //todo не хватает обработки неверного timezone здесь
            if(is_numeric($date)) return new DateTime(date('Y-m-d H:i:s', (int)$date), new DateTimeZone($timezone ?? 'Europe/Moscow'));
            if(strtotime($date)) return new DateTime($date, new DateTimeZone($timezone ?? 'Europe/Moscow'));
        } catch (\Throwable $e){
            return null;
        }
        return null;
    }

    public static function num2alpha($n)
    {
        for($r = ""; $n >= 0; $n = intval($n / 26) - 1)
            $r = chr($n%26 + 0x41) . $r;
        return $r;
    }

    public static function num2alpha2(int $n)
    {
        dump(base_convert($n, 10, 26));
        for($l = '', $arr = str_split(strrev(base_convert($n, 10, 26))), $i = count($arr) - 1; $i >= 0; $i--){
            // 2 1 0
            $l .= chr(65 + (int)(base_convert($arr[$i], 26, 10)) - !!$i);
        }
        return $l;
//        foreach(str_split(base_convert($n, 10, 26)) as $i => $letter){
//        }
//        return $l;
    }

    public static function alpha2num($string)
    {

        $alpha = str_split(preg_replace('/[^[A-Z]/', '', $string));
        for($r = 0, $i = count($alpha) - 1; $i >= 0; $i--){
            $r += pow(25, $i) + (ord($alpha[$i]) - ($i ? 64 : 65));
        }
        return $r - 1;

        $res = 0;
        dump(array_reverse(str_split($string)));
        foreach(array_reverse(str_split($string)) as $i => $l){
            dump($i);
            $res += pow(25, $i) + (ord($l) - ($i ? 64 : 65));
        }
        return $res - 1;
    }

    public static function iterableToArray(iterable $iterable): array
    {
        $array = [];
        array_push($array, ...$iterable);
        return $array;
    }

    /**
     *
     * var_dump( isValidTimeStamp(1)             ); // false
     * var_dump( isValidTimeStamp('1')           ); // TRUE
     * var_dump( isValidTimeStamp('1.0')         ); // false
     * var_dump( isValidTimeStamp('1.1')         ); // false
     * var_dump( isValidTimeStamp('0xFF')        ); // false
     * var_dump( isValidTimeStamp('0123')        ); // false
     * var_dump( isValidTimeStamp('01090')       ); // false
     * var_dump( isValidTimeStamp('-1000000')    ); // TRUE
     * var_dump( isValidTimeStamp('+1000000')    ); // false
     * var_dump( isValidTimeStamp('2147483648')  ); // false
     * var_dump( isValidTimeStamp('-2147483649') ); // false
     *
     * @param $timestamp
     * @return bool
     */
    public static function isValidTimestamp($timestamp): bool
    {
        dump((string) (int) $timestamp === (string)$timestamp);
        dump($timestamp <= PHP_INT_MAX);
        dump($timestamp >= ~PHP_INT_MAX);
        return ((string) (int) $timestamp === (string)$timestamp)
            && ($timestamp <= PHP_INT_MAX)
            && ($timestamp >= ~PHP_INT_MAX);
    }

    public static function date(?string $date, $format = 'Y-m-d H:i:s'): ?string
    {
        if(empty($date)) return null;
        return self::DateTime($date) ? self::DateTime($date)->format($format) : null;
    }
    /**
     * Конвертирует секунды в микросекунды. Полезно для удобности чтения usleep
     *
     * @param float $seconds
     * @return void
     */
    public static function microseconds(float $seconds): int
    {
        return (int)($seconds * 1000 * 1000);
    }

    /**
     * При обновлении значения CF type date амо принимает timestamp
     *
     * @param string|null $date
     * @return int|null
     */
    public static function amoDate(?string $date): ?int
    {
        return self::timestamp(self::DateTime($date));
    }

    public static function amoStr(?string $string): ?string
    {
        return (string)$string;
    }

    public static function timestamp(?DateTime $dateTime): ?int
    {
        if(!$dateTime) return null;
        return $dateTime->getTimestamp();
    }

    /**
     * @param string|null $row
     * @param string $separator
     * @return string
     */
    public static function intRow(?string $row, string $separator = ','): string
    {
        return implode($separator, self::intArray(explode($separator, $row)));
    }
    /**
     * Принимает строку, которая должна содержать только int через запятую. Например:
     * "null,4,test,123,6.7,,3," => "4,123,6,3"
     *
     * @param string|null $row
     * @param string $separator
     * @return void
     */
    public static function numericRow(?string $row, string $separator = ',')
    {
        //todo тут уже сложнее должно быть поведение чем у intRow
        // нужно сделать через вызов numericArray

//        return collect(explode($separator, ($row ?? '')))
//            ->filter(function($item){ return is_numeric($item); })
//            ->map(function($item){ return (int)$item; })
//            ->implode(',');
    }

    /**
     * Возвращает структуру для обновления кастомного поля amo v4 api
     * При этом можно передать как cfID так и cfCode (типа PHONE а не 8392432)
     * Не принимает value и enum = null одновременно.
     * Чтобы удалить значения поля нужно передавать соответствующий тип: false или ''
     * (А как в таком случае удалять значения для поля numeric?)
     * Дело в том что date поле ругается на null в value,
     * и как убрать значение date поля не совсем ясно
     *
     * @param $id
     * @param $val
     * @param $enum
     * @return array
     */
    public static function cfV4($id, $val = null, $enum = null): ?array
    {
        if(is_null($val) && is_null($enum)) return null;
        if(!$id || (!$val && !$enum)) return null;
        $cf = ['values' => [[ 'value' => $val ?? null ]]];
        if(is_numeric($id)) $cf['field_id'] = $id;
        if(gettype($id) === 'string') $cf['field_code'] = $id;
        if(count($cf) === 1) return null; // это значит что cfId и не строка и не число
        if($enum) $cf['values'][0]['enum_id'] = $enum;
        return $cf;
    }

    /**
     * Принимает произвольную строку, приводит её к snake_case
     * пробелы, дефисы, camelCase обрабатываются
     * многократное нижнее подчеркивание преобразуется к одинарному
     *
     * пример:
     * "  some_LongName-with   anyStrange __syntax" => "some_long_name_with_any_strange_syntax"
     *
     * @param string|null $input
     * @return string
     */
    public static function snakeCase(?string $input = null): string
    {
        $output = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', trim($input ?? '')));
        $output = str_replace('-', '_', $output);
        return preg_replace(['/_+/', '/\s+/'], '_', $output);
    }

    /**
     * //todo не обрабатывает SCREAMING_SNAKE_CASE
     *
     * @param string|null $input
     * @return string
     */
    public static function camelCase(?string $input = null): string
    {
        $str = self::pascalCase($input);
        $str[0] = strtolower($str[0]);
        return $str;
    }

    /**
     * //todo не обрабатывает SCREAMING_SNAKE_CASE
     *
     * @param string|null $input
     * @return string
     */
    public static function pascalCase(?string $input = null): string
    {
        return str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $input)));
    }

    /**
     * Форматирует строку как имя
     * " иМя   ФаМИЛИя  " => "Имя Фамилия"
     *
     * @param string|null $name
     * @return string|null
     */
    public static function name(?string $name): ?string
    {
        if(!$name) return null;
        return mb_convert_case(mb_strtolower(trim(preg_replace('/\s/', ' ', $name))), MB_CASE_TITLE);
    }

    /**
     * Возвращает путь разделённый корректными для системы разделителями. т.е. dir\dir\dir => dir/dir/dir
     * или наоборот, в зависимости от системы на которой исполняется функция
     *
     * @param string|null $path
     * @return string|null
     */
    public static function getPath(?string $path): ?string
    {
        if(!$path) return null;
        return str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $path);
    }

    public static function isJson($string) : bool
    {
        json_decode($string);
        return json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * Фильтрует аргумент array и возвращает массив содержащий только is_int значения из аргумента
     *
     * @param array $array
     * @return array
     */
    public static function intArray(array $array): array
    {
        return array_filter($array, 'is_int');
    }

    /**
     * приводит к инту все элементы массива-аргумента если это возможно
     * [1, '2', 3.3, 'abc'] => [1,2,3]
     *
     * @param array $array
     * @return array
     */
    public static function toIntArray(array $array): array
    {
        return array_map('intval', self::numericArray($array));
    }

    /**
     * Аналогичен toIntArray но кроме этого еще и фильтрует массив на уникальность
     *
     * @param array $array
     * @return array
     */
    public static function toIDArray(array $array): array
    {
        return array_unique(self::toIntArray($array));
    }

    /**
     * Возвращает пустой генератор.
     * Функция необходима для некоторых методов где мы хотим возвращать только генератор, а не null|Generator
     * Но вообще лучше это решать через Iterator | EmptyIterator, т.к. Generator наследуется от Iterator
     *
     * @return Generator
     */
    public static function emptyGenerator(): Generator
    {
        yield from [];
    }
    public static function toStringIterable(iterable $iterable): Collection
    {
        //todo array_filter вместо filter() коллекции использовается
        // потому что is_scalar и подобные фукнции принимают только один параметр,
        // а filter() передаёт еще и ключ после значения
        return collect(array_filter(collect($iterable)->all(), 'is_string'));
    }
    public static function toScalarIterable(iterable $iterable): Collection
    {
        //todo array_filter вместо filter() коллекции использовается
        // потому что is_scalar и подобные фукнции принимают только один параметр,
        // а filter() передаёт еще и ключ после значения
        return collect(array_filter(collect($iterable)->all(), 'is_scalar'));
    }
    public static function isStringIterable(iterable $iterable): bool
    {
        return collect($iterable)->count() === collect(self::toStringIterable($iterable))->count();
    }
    public static function isScalarIterable(iterable $iterable): bool
    {
        return collect($iterable)->count() === collect(self::toScalarIterable($iterable))->count();
    }
    public static function numericArray(array $array): array
    {
        // мы не можем сделать это через ->filter() коллекции,
        // потому что filter передает в callable аргумент 2 аргумента,
        // а is_numeric бросает ошибку если передано более 1 аргумента
        return array_filter($array, 'is_numeric');
    }
    public static function excelBool(?bool $value): string
    {
        if(is_null($value)) return '';
        return $value ? 'ДА' : 'НЕТ';
    }

    /**
     * Иногда нужно записывать значения амо мультиселекта в ексель.
     * В таком случае мы получаем через getCFV значение селекта и пишем все value через запятую
     *
     * @param mixed $value
     * @param string $delimiter
     * @return string
     */
    public static function excelMultiselect($value, string $delimiter = ','): string
    {
        return collect($value)->implode($delimiter);
    }

    /**
     * Иногда нужно записывать значения амо селекта в ексель.
     * В таком случае мы получаем через getCFV значение селекта и пишем первое value
     * Можно применять этот метод и для мультиселекта, но в таком случае будет взято первое значение,
     * а не все через запятую
     *
     * @param mixed $value
     * @return string
     */
    public static function excelSelect($value): string
    {
        return collect($value)->first() ?? '';
    }

    /**
     * Функция обратная функции excelBool (на самом деле registryBool имеется ввиду)
     *
     * @param string|null $value
     * @return bool
     */
    public static function amoBool(?string $value): bool
    {
        if(empty($value)) return false;
        return trim(mb_strtolower($value)) === 'да';
    }

    /**
     * Приводит к значению корректному для вставки в amo numeric полей
     *
     * @param string|null $value
     * @return float
     */
    public static function amoNum(?string $value): float
    {
        if(empty($value)) return 0;
        $value = preg_replace(['/,/','/[^0-9.]/'], ['.', ''], $value);
        if(!is_numeric($value)) return 0;
        return $value;
    }

    public static function excelNum(?string $value): string
    {
        return self::amoNum($value);
    }

    /**
     * Принимает числовой индекс возвращает колонку ексель соответствующую.
     * 0 => 'A', 27 => 'AB' etc
     *
     * @param int $index
     * @return int
     */
//    public static function getExcelColumnName(int $index): string
//    {
//        return PHPExcel_Cell::stringFromColumnIndex($index);
//    }

    /**
     * Принимает строку название колонки ексель
     * возвращает ее индекс
     * 'A' => 0, 'AB' = 27 etc
     *
     * @param string $column
     * @return int
     */
//    public static function getExcelColumnIndex(string $column): int
//    {
//        try{
//            return @PHPExcel_Cell::columnIndexFromString($column) - 1;
//        } catch (PHPExcel_Exception $e){
//            AmoHelper::emergency("Received wrong excel column: $column, excel error:"."{$e->getMessage()} in file {$e->getFile()} at line {$e->getLine()}");
//            return 0;
//        }
//    }

    /**
     * Принимает массив, например ['1' => 1, '2' => 2, '5' => 5]
     * Возвращает [1 => 1, 2 => 2, 5 => 5]
     *
     * @param array $array
     * @return array|false
     */
    public static function arrayToIntKeys(array $array)
    {
        $intKeys = array_map(function($key){return (int)$key;}, array_keys($array));
        return array_combine($intKeys, array_values($array));
    }

    /**
     * На самом деле спорно очень, потому что (int) "съест" ведущие нули.
     * Однако учитывая что номер телефона как правило начинается с 7 или 8 проблема не частая,но присутствует
     *
     * @param string $phone
     * @return int
     */
    public static function intPhone(string $phone): int
    {
        return (int)preg_replace('/[^0-9]/', '', $phone);
    }

    /**
     * Проверка является ли строка потенциальной инъекцией
     *
     * @return void
     */
    public static function isInjection(string $string): bool
    {
        preg_match('/[\/\\\\:<>\[\];]/iu', $string, $match);
        return !empty($match);
//        return (bool)preg_replace('/[a-z]|[а-я]|[A-Z]|[А-Я]|\d|\s/iu', '', $string);
    }

    /**
     * Нужно для сравнения имён, например, чтобы "Иван Иванов" и "Иванов Иван Иванович" считались одним именем
     * По сути разбиваем оба переданных значения на массивы содержащие элементы имени (разделитель пробел)
     * После чего находим схождение массивов и индекс схождения: отношение общих элементов к максимальному набору первоначальных
     *
     * например:
     * сравнение "Иванов Иван Иванович" и " Иван иванов" даст индекс 0.66 (т.е. совпадение 2/3 элементов имени),
     * и мы будем считать это одинаковым именем.
     *
     * важно:
     * сравнение "Иванович Иван" и "Иванович Иван Иванов" тоже даст 0.66, и мы будем дуать что это одно имя
     * хотя сочетание Имя + Отчество и гораздо менее уникально чем ФИО
     *
     * @param string $template
     * @param string $target
     * @return bool
     */
    public static function isSameNames(string $template, string $target): bool
    {
        $template = collect(explode(' ', self::name($template)));
        $target = collect(explode(' ', self::name($target)));
        $intersection = $template->intersect($target);
        $intersectionIndex = $intersection->count() / max($template->count(), $target->count());
        return $intersectionIndex >= 0.6;
    }

    /**
     * Приводит произвольное значение в приемлемое для амо типа поля price
     *
     * @param mixed $price
     * @return float
     */
    public static function amoPrice($price): float
    {
        if(!is_string($price) && !is_int($price) && !is_float($price)) return 0;
        $price = preg_replace('/\s/u', '', $price);
        $price = str_replace(',', '.', $price);
        if(!is_numeric($price)) return 0;
        return round((float)$price, 2);
    }

    /**
     * Сравнивает адреса по сегментам, считает что адрес одинаковый если:
     * совпадает 60 или более процентов сегментов (для адресов более чем из 3х сегментов).
     * совпадает 70 или более процентов сегментов (для адресов менее чем из 3х сегментов). (это на самом деле бессмысленно т.к. тут или 66 или 100)
     *
     * разные:
     * "Москва, Каширское ш., 26к2"
     * "Москва, Каширское ш., 65к3"
     *
     * одинаковые:
     * "Краснодарский край, Кореновский р-н, Кореновское городское поселение, Кореновск, Красная ул., 122"
     * "Краснодарский край, Кореновск, Красная улица, 122"
     *
     * @param string|null $address1
     * @param string|null $address2
     * @return bool
     */
    public static function compareAddresses(?string $address1, ?string $address2): bool
    {
        if(empty($address1) || empty($address2)) return false;
        $address1 = collect(explode(',', self::prepareAddressToCompare($address1)))->map(function($part){ return trim($part); });
        $address2 = collect(explode(',', self::prepareAddressToCompare($address2)))->map(function($part){ return trim($part); });
        $intersection = $address1->intersect($address2);
        $intersectionIndex = $intersection->count() / max($address1->count(), $address2->count());
        // чем меньше составных у адреса тем точнее мы должны сравнивать.
        // по сути для трёхсоставных адресов совпадение должно быть 100%-ым
        if(max($address1->count(), $address2->count()) < 4) return $intersectionIndex >= 0.7; // если
        // без этого условия считаются одинаковыми:
        // Московская обл., Орехово-Зуевский г.о., Ликино-Дулёво, ул. Ленина, 4/1
        // Московская обл., Орехово-Зуевский г.о., Орехово-Зуево, ул. Ленина, 50А
        if(abs($address1->count() - $address2->count()) < 2) return $intersectionIndex >= 0.7;
        // точность ниже в основном нужна для ситуаций когда сравниваем адрес где указан полный адрес (много сегментов)
        // с адресом где только город улица и дом:
        // Краснодарский край, Кореновск, Красная улица, 122
        // Краснодарский край, Кореновский р-н, Кореновское городское поселение, Кореновск, Красная ул., 122
        return $intersectionIndex >= 0.6;
    }

    /**
     * Приводит строку с адресом к единому виду:
     *
     * Московская область, Орехово-Зуевский городской округ, Ликино-Дулёво, улица Ленина, 4/1
     * => московская обл, орехово-зуевский го, ликино-дулeво, ул ленина, 4/1
     *
     * Московская обл., Орехово-Зуевский г.о., Ликино-Дулёво, ул. Ленина, 4/1
     * => московская обл, орехово-зуевский го, ликино-дулeво, ул ленина, 4/1
     *
     * @param string $address
     * @return string
     */
    private static function prepareAddressToCompare(string $address): string
    {
        // замена будет производится в порядке значение => ключ
        // т.к. например "ул" может быть в составе другого слова ("переУЛок")
        $reductions = collect([
            'улица' => 'ул',
            'проспект' => 'пр-т',
            'область' => 'обл',
            'автономный округ' => 'ао',
            'административный округ' => 'ао',
            'район' => 'р-н',
            'проезд' => 'пр',
            'городской округ' => 'го',
            'микрорайон' => 'мкр-н',
            'бульвар' => 'б-р',
            'переулок' => 'пер',
            'площадь' => 'пл',
            'дачный поселок' => 'дп',
            'шоссе' => 'ш',
            'рабочий поселок' => 'рп',
        ]);

        // убираем лишние пробелы и приводим к нижнему регистру
        $address = trim(mb_strtolower($address));
        // множественные пробельные символы и переводы строк заменяем на одиночный пробел
        $address = preg_replace(['/\s+/', '/\n+/'], ' ', $address);
        // все точки удаляем, запятые остаются потому что по ним мы будем разбивать на сегменты адрес в дальнейшем
        $address = str_replace('.', '', $address);
        // избавляемся от ё
        $address = str_replace('ё', 'е', $address);

        return str_replace($reductions->keys()->all(), $reductions->values()->all(), $address);
    }


//    public static function cfv4($cfId, $value, $enum = null): array
//    {
//        if(!is_int($cfId) && !is_string($cfId))
//            throw new InvalidArgumentException('cfId must be integer or string (cf code) types');
//        $values = collect($value);
//        if($values->isNotEmpty() && ! self::isScalarIterable($values))
//            throw new InvalidArgumentException('value must be iterable[scalar] or scalar types');
//        $enums = collect($enum);
//        if($enums->isNotEmpty() && ! self::isScalarIterable($enums))
//            throw new InvalidArgumentException('enum must be iterable[scalar] or scalar types');
//        if(empty($cfId) || ($values->isEmpty() && $enums->isEmpty())) return [];
//
//        if(is_numeric($cfId)) $cf['field_id'] = (int)$cfId;
//        if(is_string($cfId)) $cf['field_code'] = (string)$cfId;
//
//        if($enums->isNotEmpty()){
//            $cf['values'] = $enums->map(function($enum){
//                if(is_numeric($enum)) return ['enum_id' => (int)$enum];
//                if(is_string($enum)) return ['enum_code' => (string)$enum];
//                return [];
//            })->all();
//        } else {
//            $cf['values'] = $values->map(function($value){
//                return ['value' => $value];
//            })->all();
//        }
//
//        return $cf;
//    }


}