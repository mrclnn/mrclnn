<?php

namespace App\Http\Controllers;



use App\GalleryTagAggregator;
use App\Helper;
use App\Http\Controllers\Controller;
use App\Jobs\ParserJob;
use App\Models\Categories;
use App\Models\Posts;
use App\Services\NotaloneService;
use DateTime;
use DateTimeZone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Monolog\Handler\PsrHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;
use Monolog\Processor\PsrLogMessageProcessor;
use Psr\Log\LoggerAwareTrait;
use simplehtmldom\HtmlWeb;
use Throwable;

class AjaxController extends Controller
{
    use LoggerAwareTrait;
    private array $request = [];
    private array $response = [];
    public function execute(Request $request){

//        $helper = new Helper();
        set_error_handler(function(int $errno, string $errstr, string $errfile, int $errline){
            $helper = new Helper();
            $helper->sendToTG("[$errno] $errstr at line $errline");
            return response(json_encode(['success' => false, 'message' => 'internal server error']));
        });

        $this->setLogger();
        $this->request = $request->all();
        $requestTimeBegin = microtime(true);
        $this->logger->info('request: ', $this->request);

        try{
            $answer = $this->processQuery();
            $this->response = $answer;
        } catch (Throwable $e){
            $this->logger->error($e->getMessage() . ' in file ' . $e->getFile() . ' at line ' . $e->getLine());
            $this->response = ['success' => false, 'message' => $e->getMessage() . ' at line ' . $e->getLine() . ' in file ' . $e->getFile()];
//            $helper->sendToTG($e->getMessage() . ' in file ' . $e->getFile() . ' at line ' . $e->getLine());
        } finally {
            $this->logger->info(
                sprintf('time : %s response: ', microtime(true) - $requestTimeBegin),
                count($this->response, COUNT_RECURSIVE) > 5 ?
                    ['response_count' => count($this->response, COUNT_RECURSIVE)] :
                    $this->response
            );
            return response(json_encode($this->response));
        }
    }
    private function processQuery() : ?array
    {

        if(isset($this->request['action'])){
            $actionHandlerName = $this->request['action'] . 'Query';
            if(method_exists(self::class, $actionHandlerName)){
                return $this->$actionHandlerName();
            }
            //todo написать текст ошибки
            throw new InvalidArgumentException("Received wrong action $actionHandlerName");

        }

        return [];
    }
    private function getQuery() : array
    {
        // screen - это float соотношение сторон экрана у запросившего устройства. обычно от 0.4 до 1.8
        // в качестве дефолтного значения можно оставить 1 для квадратных изображений
        $screen = (float)$this->request['screen'] ?? 1;
        $requestedCategory = (string)$this->request['category'];

        //todo нормализовать возвращаемые значения. например CategoryAggregator::getFromName возвращает null если не найдено
        // в то время как TagAggregator::getFromName будет выбрасывать исключение
        $category = Categories::getFromName($requestedCategory) ?? Categories::getFromTag($requestedCategory);

        // если нет категории то возможно запросили просто тэг
        if(!$category || !$category->enabled){
            $this->logger->info("requested category $requestedCategory not found or disabled");
            return [];
        }

        $this->logger->info("requested category $requestedCategory found in list of enabled");
        $answer = [
            'info' => [
                'count' => $category->count
            ]
        ];
        //todo это клиент нам должен говорить сколько постов необходимо получить
        $posts = $category->getPostsForSlider(40, $screen);

        $answer['posts'] = $posts->map(function($post){
            return [
                'id' => $post->id,
                'file_name' => $post->file_name,
                'shown' => false,
                'status' => $post->status,
                'width' => $post->width,
                'height' => $post->height,
                'tags' => $post->getTagsList(),
            ];
        })->toArray();

        return $answer;

    }
    private function searchTagQuery() : array
    {
        $searchWord = (string)$this->request['searchTag'];
        $foundTags = GalleryTagAggregator::searchTag($searchWord);
        return [
            'tags' => array_values(array_filter($foundTags, function($tag){return !!$tag->enabled;}))
        ];
    }
    private function addCategoryQuery() : array
    {
        //todo он может выбрасывать ошибку потому что name поле в базе данных уникальное и никак не проверяется на Php
        $category = (new Categories)
            ->setExtendTags((string)$this->request['extendTags'])
            ->setExcludeTags((string)$this->request['excludeTags'])
            ->setIncludeTags((string)$this->request['includeTags'])
            ->setName((string)$this->request['name'])
            ->setCount((int)$this->request['count']);
        $success = $category->save();
        if($success) Posts::addCategoryID($category);
        return [
            'success' => $success
        ];
    }
    private function updateCategoryQuery(): array
    {
        //todo проверку типа получаемых данных
        $categoryID = (int)$this->request['id'];
        $category = Categories::getFromID($categoryID);
        if(!$category) return [
            'success' => false,
            'message' => "Not found category with id $categoryID"
        ];
        Posts::removeCategoryID($category);
        //todo он может выбрасывать ошибку потому что name поле в базе данных уникальное и никак не проверяется на Php
        $success = $category
            ->setIncludeTags((string)$this->request['includeTags'])
            ->setExcludeTags((string)$this->request['excludeTags'])
            ->setExtendTags((string)$this->request['extendTags'])
            ->setName((string)$this->request['name'])
            ->save();
        Posts::addCategoryID($category);

        return [
            'success' => $success,
        ];
    }
    private function checkCategoryCount(): array
    {
        $category = Categories::getFakeCategory()
            ->setExtendTags((string)$this->request['extendTags'])
            ->setExcludeTags((string)$this->request['excludeTags'])
            ->setIncludeTags((string)$this->request['includeTags'])
            ->reCount();

        return [
            'count' => $category->count
        ];
    }
    private function deleteCategoryQuery(): array
    {
        $category = Categories::getFromID((int)$this->request['categoryID']);
        if($category) $category->delete();
        return ['success' => true ];
    }
    private function recountCategoryQuery(): array
    {
        //todo проверка параметра
        $category = Categories::getFromID((int)$this->request['categoryID']);
        $success = $category && !!$category->reCount()->save();
        return [
            'success' => $success,
            'id' => $category->id,
            'count' => $category->count,
        ];
    }
    private function notalone(){
        return $this->saveDie();
        return NotaloneService::process($this->request);
    }
    private function duplicatesQuery() : array
    {

        $affected = Posts::setDuplicates($this->request['duplicates']);
        return [$affected];
//        $all_posts = [];
//        $category_name = '';
//        $category_id = 0;
//        while($all_posts === []){
//            $category = DB::select('SELECT id, name FROM categories hc WHERE hc.rank = 0 and hc.type = 1 ORDER BY id LIMIT 1');
//            $category_id = $category[0]->id;
//            $category_name = $category[0]->name;
//            $all_posts = DB::select(' AND hp.category_id = ?
//ORDER BY tags_character DESC, hash
//        \'', [$category_id]);
//
//            if($all_posts === []){
//                $affected = DB::table('categories')->where('id', $category_id)->update(['rank' => 9]);
//            }
//        }
//        $characters = [];
//        $ih = new ImageHash();
//        foreach ($all_posts as $post){
//            if(!isset($characters[$post->tags_character])) $characters[$post->tags_character] = [];
//            $characters[$post->tags_character][] = [
//                'file' => $post->file_name,
//                'hash' => $ih->createHashFromFile(public_path('/img/'.$post->file_name), 15, 6, true),
////                'hash' => $post->hash,
//                'id' => $post->id
//            ];
//        }
//
//        $duplicates = [];
//        $limit = 0;
//        $processed = [];
//        $progress = 0;
//        foreach ($characters as $charTag => $char){
//            $limit++;
//            $progress++;
//            if($limit > 60) break;
//            foreach ($char as $post){
//                if(in_array($post['id'], $processed)) continue;
//                $processed[] = $post['id'];
//                $dupl = [$post['id'] => ['src' => $post['file']]];
//                foreach ($char as $_post){
//                    if(in_array($_post['id'], $processed)) continue;
//                    if($ih->compareImageHashes($post['hash'], $_post['hash'], 0.15)){
//                        $dupl[$_post['id']] = ['src' => $_post['file']];
//                        $processed[] = $_post['id'];
//                    }
//                }
//                if(count($dupl) > 1) $duplicates[] = $dupl;
//            }
//        }
//        if($duplicates === []){
//            $affected = DB::table('categories')->where('id', $category_id)->update(['rank' => 9]);
//            return $this->duplicatesQuery();
//        }
////        return ['success' => true, 'blabla' => 'here'];
////        $this->eventStreamMessage('end');
//        return ['success' => true, 'env' => ['tag_name' => $category_name, 'tag_id' =>$category_id, 'dupl' => $duplicates]];
    }
    private function setRank(int $catId, int $rank, array $rejectedIDs){

        return $this->saveDie();
        $affected = DB::table('categories')->where('id', $catId)->update(['rank' => $rank]);
        if(!empty($rejectedIDs)){
            DB::select(sprintf('UPDATE posts SET status = 3, estimate_at = %s WHERE id IN (%s)', '\''.$this->getTimeForDB().'\'',implode(',', $rejectedIDs)));
        }
        $affected = 1;
        if ($affected === 1){
            return ['success' => true, 'message' => 'successfully updated'];
        }
        return ['success' => false, 'message' => 'affected: '.$affected];

    }
    private function duplicatePreload(int $offset){
        return $this->saveDie();
        $limit = 50;
        $posts = DB::select('
SELECT file_name as src, tag, hp.id as id, category_id
FROM posts AS hp
JOIN 
(SELECT id, tag FROM categories 
WHERE deleted_at IS NULL and enabled = 1 AND type = 1 AND rank = 0
ORDER BY id
LIMIT 1) AS category
WHERE hp.category_id = category.id AND hp.status != 3
ORDER BY tags_character desc, hash
LIMIT ?
OFFSET ?
', [$limit, $limit * $offset]);
        return ['success' => true, 'content' => $posts];
    }
    private function duplicatePost(int $id){
        return $this->saveDie();
        $affected = DB::table('posts')->where('id', $id)->update(['status' => 3, 'estimate_at' => $this->getTimeForDB()]);
        if($affected === 1){
            return ['success' => true, 'message' => 'deleted successfully post: '.$id];
        } else {
            return ['success' => false, 'message' => 'affected: '.$affected];
        }

    }
    private function rejectAuthors(string $tag) : array
    {
        $this->saveDie();
        $affected = DB::table('needle_authors')->where('author', $tag)->update(['processed' => 2]);
        if($affected !== 1) return ['success' => false, 'reason' => 'affected processed = 2 needle_authors = ' . $affected];
        if(!$this->clearTmpDir($tag)) return ['success' => false, 'reason' => 'failed to clear tmp files'];
        return ['success' => true];
    }
    private function loadAuthors()
    {
        return $this->saveDie();
        $author = DB::select('SELECT author, author_alias FROM needle_authors WHERE processed = 0 AND preloaded = 1 ORDER BY id');
        $all = count($author);
        if(!$author) return ['failed' => false, 'empty' => true, 'reason' => 'empty select query needle_authors'];
        $tag = $author[0]->author;
        if(!$tag) return ['failed' => true, 'reason' => 'empty tag from query needle_authors'];
        $alias = $author[0]->author_alias ?: $this->getTagAlias($tag);
        try{
            $regex = public_path('/img/tmp/') . $alias . '_*';
            $srcList = array_map(function($fileName){
                return str_replace('/home/u946280762/domains/mrclnn.com/public_html/', 'https://mrclnn.com/', $fileName);
            }, glob($regex));
            if(!$srcList){
                $regex = public_path('/img/tmp/') . $tag . '_*';
                $srcList = array_map(function($fileName){
                    return str_replace('/home/u946280762/domains/mrclnn.com/public_html/', 'https://mrclnn.com/', $fileName);
                }, glob($regex));
            }

            return ['tag' => $tag, 'src' => $srcList, 'count' => $this->getArtistCount($tag), 'all' => $all];
        } catch (Throwable $e){
            return ['failed' => true, 'reason' => $e->getMessage() . ' in file ' . $e->getFile() . ' at line ' . $e->getLine()];
        }
    }
    private function getArtistCount(string $tag) : ?int
    {
        $url_tag = str_replace(['+', '\''],['%2b', '&#039;'], $tag);
        $url = 'test/autocomplete.php?q=' . $url_tag;
        $this->logger->info('ARTIST COUNT RUN...');
        $doc = new HtmlWeb();
        $json = $doc->load($url);
        $res = json_decode($json);
        foreach ($res as $author){
            if(preg_match('/^\(\d+\)$/',trim(str_replace($tag, '', $author->label)))){
                if(count($res) >= 1){
                    $count = str_replace([$tag, '(', ')'], '',$author->label);
                    $this->logger->debug($tag . ' : ' . $count);
                    return (int)$count;
                }
                if(count($res) === 0) {
                    $this->logger->warning('not found tag ' . $tag);
                    return null;
                }
                $this->logger->warning('get less than 1 result for '.$tag.':', $res);
                return null;
            }
        }
        $this->logger->warning('not found tag ' . $tag . ' in answer json: ' . $json);
        return null;
    }
    private function saveDie(): array
    {
        $stuckTrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        $function = $stuckTrace[0]['function'] ?? '';
        return ['success' => false, 'message' => "temporary unavailable function: $function"];
    }
    public function load() : array
    {

        return $this->saveDie();
        try{
            $author_tag = (string)$this->request['tag'];
            $this->logger->info('Received request to dispatch category ' . $author_tag . ' ...');
            $cat = DB::select('SELECT id FROM categories WHERE tag = ?', [$this->request['load']]);
            if(empty($cat) || $cat[0]->id == 1){
                dispatch(new ParserJob($this->request['load']));
                $this->clearTmpDir($this->request['load']);
                $this->logger->info('Dispatching category ' . $author_tag . ' ...');
                $affected = DB::table('needle_authors')->where('author', $author_tag)->update(['processed' => 1]);
                if($affected !== 1){
                    $this->logger->warning('affected needle_authors = ' . $affected);
                    return ['success' => true, 'reason' => 'affected needle_authors = ' . $affected];
                }
                return ['success' => true];
            }
            $this->logger->info('Category ' . $author_tag . 'already exist. Skip.');
            $affected = DB::table('needle_authors')->where('author', $author_tag)->update(['processed' => 1]);
            if($affected !== 1){
                return ['success' => false, 'reason' => 'update processed query returned ' . $affected . ' rows.'];
            }
            return ['success' => true, 'reason' => 'category already exist. id = ' . $cat[0]->id];
        } catch (Throwable $e){
            return ['success' => false, 'reason' => $e->getMessage()];
        }
    }
    private function estimateQuery() : array
    {
        $requestedPostID = (int)$this->request['post'];
        $requestedPostStatus = (int)$this->request['status'];

        $requestedPost = Posts::getFromID($requestedPostID);
        if(!$requestedPost) return [
            'success' => false,
            'error' => "Not found post ($requestedPostID)"
        ];
        Helper::test($this->logger);
        if($requestedPostStatus === Posts::POST_STATUS_FAVORITE) $requestedPost->estimate();
        if($requestedPostStatus === Posts::POST_STATUS_DISABLED) $requestedPost->disable();
        Posts::setShowed([$requestedPostID]);

        return [
            'success' => true,
            'message' => "post ($requestedPostID) estimated successfully",
        ];
    }
    private function insertRecordToHNA(string $tags_artist, int $estimate) : array
    {
        if($tags_artist === ''){
            return ['success' => true, 'message' => 'Empty tags_artist received.'];
        }
        $info = '';
        foreach (explode(' ', $tags_artist) as $tag){
            $info .= 'tag ' . $tag;
            $isNew = !DB::select(')', [$tag]);
            $info .= $isNew ? ' is new.' : ' already exist.';
            if(!$isNew) return ['success' => true, 'message' => $info];

            if($estimate === 0){
                $processed = 3;
            } elseif($estimate === 2){
                $processed = 0;
                $this->dispatch(new ParserJob($tag, 16));
            }
            // записываем в hna полученный тег
            $success = DB::table('needle_authors')->insert(
                ['author' => $tag, 'author_alias' => $this->getTagAlias($tag), 'processed' => $processed]);
            if($processed === 0){
                $info .= $success ? ' added successfully. ' : ' failed to add. ';
            } else {
                $info .= $success ? ' rejected successfully. ' : ' failed to reject. ';
            }
            // отмечаем все посты с таким тегом
            DB::select('UPDATE posts SET debug = 1 WHERE category_id = 40 AND LOCATE(?, tags_artist) != 0', [$tag]);

        }
        return ['success' => true, 'message' => $info];
    }
    private function searchQuery() : array
    {
        return $this->saveDie();
//        $parser = ParserAggregator::getParser('search');
//        // todo не безопасно
//        $json = $parser->parse([$this->request['word']])['page'];
//        return json_decode($json, true);
    }
    private function shownQuery() : array
    {
        $idList = $this->request['posts'];
        if(Helper::isIntListArray($idList, true)){
            $affected = Posts::setShowed($this->request['posts']);
            return ['success' => true, 'affected' => $affected, 'message' => $affected . ' rows shown affected successfully.'];
        }
        //todo написать текст ошибки
        return ['success' => false, 'message' => 'invalid argument'];

    }
    private function setLogger() : void
    {
        $this->logger = new Logger(
            'queries', [

            new PsrHandler(app()->make('log'), Logger::WARNING),
            new RotatingFileHandler(storage_path('logs/queries.log'), 14, Logger::DEBUG, true, 0664),

        ], [
                new PsrLogMessageProcessor(),
            ]
        );
    }
    private function getTimeForDB() : string
    {
        $date = new DateTime();
        $date->setTimezone(new DateTimeZone('Europe/Minsk'));
        return $date->format('Y.m.d H:i:s');
    }
    private function getTagAlias(string $tag) : string
    {
        preg_match_all('/[a-zA-Z0-9]*/', $tag, $res);
        $prefix = implode('', $res[0]);
        return $prefix . '_' . hash('md5', $tag);
    }
    private function clearTmpDir(string $tag)
    {
        $this->logger->info('try to clear tmp files.');
        $alias = $this->getTagAlias($tag);
        $regex = public_path('/img/tmp/') . $alias . '_*';
        $files = glob($regex);
        $res = array_map("unlink", $files);
        foreach ($res as $i => $success){
            if(!$success){
                $this->logger->warning('Unable to unlink file ' . $files[$i]);
            }
        }
        $this->logger->info('successfully cleared ' . count($res) . ' files.');
        return true;
    }
}
