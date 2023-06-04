<?php

namespace App\Http\Controllers\Gallery;



use App\GalleryTagAggregator;
use App\Helper;
use App\Http\Controllers\Controller;
use App\Jobs\ParserJob;
use App\Models\Categories;
use App\Models\Posts;
use App\Services\NotaloneService;
use DateTime;
use DateTimeZone;
use Illuminate\Http\JsonResponse;
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

    public function __construct()
    {
        $this->setLogger();
    }

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
    public function get(Request $request) : JsonResponse
    {

        // screen - это float соотношение сторон экрана у запросившего устройства. обычно от 0.4 до 1.8
        // в качестве дефолтного значения можно оставить 1 для квадратных изображений
        $screen = $request->input('screen', 1);
        $requestedCategory = $request->input('category', 1);

        $category = Categories::getFromName($requestedCategory) ?? Categories::getFromTag($requestedCategory);

        // если нет категории то возможно запросили просто тэг
        if(!$category || !$category->enabled){
            $this->logger->info("requested category $requestedCategory not found or disabled");
            return response()->json();
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

        return response()->json($answer);

    }
    public function estimate(Request $request) : JsonResponse
    {

        $requestedPostID = $request->input('post');
        $requestedPostStatus = (int)$request->input('status', 1);

        $requestedPost = Posts::getFromID($requestedPostID);
        //todo сделать кастомный класс ответа чтобы он содержал в себе по умолчанию поля success message body error, методы для заполнения этих полей
        if(!$requestedPost) return response()->json([
            'success' => false,
            'message' => null,
            'body' => null,
            'error' => "Not found post ($requestedPostID)",
        ]);
        //todo здесь можно засунуть в один метод estimate любые оценки
        if($requestedPostStatus === Posts::POST_STATUS_FAVORITE) $requestedPost->estimate();
        if($requestedPostStatus === Posts::POST_STATUS_DISABLED) $requestedPost->disable();
        Posts::setShowed([$requestedPostID]);

        return response()->json([
            'success' => true,
            'message' => "post ($requestedPostID) estimated successfully",
            'body' => null,
            'error' => null,
        ]);
    }
    public function shown(Request $request) : JsonResponse
    {

        $idList = $request->input('posts', []);
        if($idList && Helper::isIntListArray($idList, true)){
            $affected = Posts::setShowed($idList);
            return response()->json([
                'success' => true,
                'message' => $affected . ' rows shown affected successfully.',
                'body' => [
                    'affected' => $affected
                ],
                'error' => null,
            ]);
        }
        //todo написать текст ошибки
        return response()->json([
            'success' => false,
            'message' => 'invalid argument',
            'body' => null,
            'error' => null,
        ]);

    }
    public function deleteCategory(Request $request): JsonResponse
    {
        $category = Categories::getFromID($request->input('categoryID'));
        if($category) $name = $category->name; $category->delete();
        return response()->json([
            'success' => true,
            'message' => "Category $name deleted successfully",
            'error' => null,
            'body' => null,
        ]);
    }
    public function recountCategory(Request $request): JsonResponse
    {
        //todo проверка параметра

        $category = Categories::getFromID($request->input('categoryID'));
        $success = $category && !!$category->reCount()->save();
        return response()->json([
            'success' => $success,
            'message' => "category $category->name recounted successfully",
            'error' => null,
            'body' => [
                'id' => $category->id,
                'count' => $category->count,
            ],
        ]);
    }
    public function updateCategory(Request $request): JsonResponse
    {
        //todo проверку типа получаемых данных
        $categoryID = $request->input('id');
        $category = Categories::getFromID($categoryID);
        if(!$category) return response()->json([
            'success' => false,
            'message' => "Not found category with id $categoryID",
            'error' => null, //todo ну вот это странно
            'body' => null
        ]);
        Posts::removeCategoryID($category);
        //todo он может выбрасывать ошибку потому что name поле в базе данных уникальное и никак не проверяется на Php
        $success = $category
            ->setIncludeTags($request->input('includeTags', ''))
            ->setExcludeTags($request->input('excludeTags', ''))
            ->setExtendTags($request->input('extendTags', ''))
            ->setName($request->input('name', ''))
            ->save();
        Posts::addCategoryID($category);

        return response()->json([
            'success' => $success,
            'message' => "category successfully updated . . . ",//todo дописать эту херь
            'error' => null,
            'body' => null,
        ]);
    }
    public function searchTag(Request $request) : JsonResponse
    {
        $searchWord = $request->input('searchTag');
        $foundTags = GalleryTagAggregator::searchTag($searchWord);
        return response()->json([
            'success' => true,
            'message' => '', //todo
            'error' => null,
            'body' => [
                'tags' => array_values(array_filter($foundTags, function($tag){return !!$tag->enabled;}))
            ],
        ]);
    }
    public function addCategory(Request $request) : JsonResponse
    {
        //todo он может выбрасывать ошибку потому что name поле в базе данных уникальное и никак не проверяется на Php
        $category = (new Categories)
            ->setExtendTags($request->input('extendTags'))
            ->setExcludeTags($request->input('excludeTags'))
            ->setIncludeTags($request->input('includeTags'))
            ->setName($request->input('name'))
            ->setCount($request->input('count'));
        $success = $category->save();
        if($success) Posts::addCategoryID($category);
        return response()->json([
            'success' => $success,
            'error' => null, //todo
            'message' => '',
            'body' => []
        ]);
    }

    public function checkCategoryCount(Request $request): JsonResponse
    {
        $category = Categories::getFakeCategory()
            ->setExtendTags($request->input('extendTags'))
            ->setExcludeTags($request->input('excludeTags'))
            ->setIncludeTags($request->input('includeTags'))
            ->reCount();

        return response()->json([
            'success' => true,
            'message' => '',
            'error' => null,
            'body' => [
                'count' => $category->count,
            ],
        ]);
    }




    public function search() : array
    {
        return $this->saveDie();
//        $parser = ParserAggregator::getParser('search');
//        // todo не безопасно
//        $json = $parser->parse([$this->request['word']])['page'];
//        return json_decode($json, true);
    }
    public function load() : array
    {

        return $this->saveDie();
//        try{
//            $author_tag = (string)$this->request['tag'];
//            $this->logger->info('Received request to dispatch category ' . $author_tag . ' ...');
//            $cat = DB::select('SELECT id FROM categories WHERE tag = ?', [$this->request['load']]);
//            if(empty($cat) || $cat[0]->id == 1){
//                dispatch(new ParserJob($this->request['load']));
//                $this->clearTmpDir($this->request['load']);
//                $this->logger->info('Dispatching category ' . $author_tag . ' ...');
//                $affected = DB::table('needle_authors')->where('author', $author_tag)->update(['processed' => 1]);
//                if($affected !== 1){
//                    $this->logger->warning('affected needle_authors = ' . $affected);
//                    return ['success' => true, 'reason' => 'affected needle_authors = ' . $affected];
//                }
//                return ['success' => true];
//            }
//            $this->logger->info('Category ' . $author_tag . 'already exist. Skip.');
//            $affected = DB::table('needle_authors')->where('author', $author_tag)->update(['processed' => 1]);
//            if($affected !== 1){
//                return ['success' => false, 'reason' => 'update processed query returned ' . $affected . ' rows.'];
//            }
//            return ['success' => true, 'reason' => 'category already exist. id = ' . $cat[0]->id];
//        } catch (Throwable $e){
//            return ['success' => false, 'reason' => $e->getMessage()];
//        }
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
