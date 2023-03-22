<?php

namespace App\Http\Controllers;



use App\GalleryCategoryAggregator;
use App\GalleryCategoryModel;
use App\GalleryPostAggregator;
use App\GalleryTagAggregator;
use App\Helper;
use App\Jobs\ParserJob;
use App\Services\ImageHash;
use App\Services\NotaloneService;
use DateTime;
use DateTimeZone;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
//        $helper->sendToTG('request: '. implode(' ',$this->request));

        try{
            $answer = $this->processQuery();
            $this->response = $answer;
        } catch (Throwable $e){
            $this->logger->error($e->getMessage() . ' in file ' . $e->getFile() . ' at line ' . $e->getLine());
            $this->response = ['success' => false, 'message' => $e->getMessage() . ' at line ' . $e->getLine()];
//            $helper->sendToTG($e->getMessage() . ' in file ' . $e->getFile() . ' at line ' . $e->getLine());
        } finally {
            $this->logger->info(sprintf('time : %s response: ', microtime(true) - $requestTimeBegin), count($this->response)>5?['response_count' => count($this->response)]:$this->response);
            return response(json_encode($this->response));
        }
    }
    private function processQuery() : ?array
    {
        if(isset($this->request['get'])) return $this->getQuery();
        if(isset($this->request['searchTag'])) return $this->searchTagQuery();
        if(isset($this->request['addCategory'])) return $this->addCategoryQuery();
        if(isset($this->request['deleteCategory'])) return $this->deleteCategoryQuery();
        if(isset($this->request['checkCategoryCount'])) return $this->checkCategoryCount();
        if(isset($this->request['estimate'])) return $this->estimateQuery();
        if(isset($this->request['search'])) return $this->searchQuery();
        if(isset($this->request['shown'])) return $this->shownQuery();
        if(isset($this->request['load'])) return $this->loadQuery($this->request['load']);
        if(isset($this->request['authors'])) return $this->loadAuthors();
        if(isset($this->request['reject_authors'])) return $this->rejectAuthors($this->request['reject_authors']);
        if(isset($this->request['duplicate'])) return $this->duplicatePost($this->request['duplicate']);
        if(isset($this->request['duplPreload'])) return $this->duplicatePreload($this->request['duplPreload']);
        if(isset($this->request['duplicates'])) return $this->duplicatesQuery();
        if(isset($this->request['setRank'])){
            $rejectedIDs = $this->request['rejectedIDs'] ?? [];
            return $this->setRank($this->request['setRank'], $this->request['rank'], $rejectedIDs);
        }
        if(isset($this->request['notalone'])){return $this->notalone();}

        return null;
    }

    private function searchTagQuery() : array
    {
        $searchWord = (string)$this->request['searchTag'];
        $foundTags = GalleryTagAggregator::searchTag($searchWord);
        return [
            'tags' => $foundTags
        ];
    }

    private function addCategoryQuery() : array
    {
        $categoryName = (string)$this->request['name'];
        $associatedTags = (string)$this->request['associatedTags'];
        $count = (int)$this->request['count'];
        $success = GalleryCategoryAggregator::addCategory($categoryName, $associatedTags, $count);
        return [
            'success' => $success
        ];
    }

    private function checkCategoryCount()
    {
        $associatedTags = (string)$this->request['associatedTags'];
        $preCategoryCount = GalleryCategoryAggregator::checkAssociatedTagsCount($associatedTags);
        return [
            'count' => $preCategoryCount
        ];
    }

    private function deleteCategoryQuery()
    {
        $categoryID = (int)$this->request['categoryID'];
        $success = GalleryCategoryAggregator::deleteCategory($categoryID);
        return [
            'success' => $success
        ];
    }

    private function notalone(){
        return NotaloneService::process($this->request);
    }
    private function duplicatesQuery() : array
    {

//        return ['hi'];
        $all_posts = [];
        $category_name = '';
        $category_id = 0;
        while($all_posts === []){
            $category = DB::select('SELECT id, name FROM categories hc WHERE hc.rank = 0 and hc.type = 1 ORDER BY id LIMIT 1');
            $category_id = $category[0]->id;
            $category_name = $category[0]->name;
            $all_posts = DB::select(' AND hp.category_id = ?
ORDER BY tags_character DESC, hash
        \'', [$category_id]);

            if($all_posts === []){
                $affected = DB::table('categories')->where('id', $category_id)->update(['rank' => 9]);
            }
        }
        $characters = [];
        $ih = new ImageHash();
        foreach ($all_posts as $post){
            if(!isset($characters[$post->tags_character])) $characters[$post->tags_character] = [];
            $characters[$post->tags_character][] = [
                'file' => $post->file_name,
                'hash' => $ih->createHashFromFile(public_path('/img/'.$post->file_name), 15, 6, true),
//                'hash' => $post->hash,
                'id' => $post->id
            ];
        }

        $duplicates = [];
        $limit = 0;
        $processed = [];
        $progress = 0;
        foreach ($characters as $charTag => $char){
            $limit++;
            $progress++;
            if($limit > 60) break;
            foreach ($char as $post){
                if(in_array($post['id'], $processed)) continue;
                $processed[] = $post['id'];
                $dupl = [$post['id'] => ['src' => $post['file']]];
                foreach ($char as $_post){
                    if(in_array($_post['id'], $processed)) continue;
                    if($ih->compareImageHashes($post['hash'], $_post['hash'], 0.15)){
                        $dupl[$_post['id']] = ['src' => $_post['file']];
                        $processed[] = $_post['id'];
                    }
                }
                if(count($dupl) > 1) $duplicates[] = $dupl;
            }
        }
        if($duplicates === []){
            $affected = DB::table('categories')->where('id', $category_id)->update(['rank' => 9]);
            return $this->duplicatesQuery();
        }
//        return ['success' => true, 'blabla' => 'here'];
//        $this->eventStreamMessage('end');
        return ['success' => true, 'env' => ['tag_name' => $category_name, 'tag_id' =>$category_id, 'dupl' => $duplicates]];
    }
    private function setRank(int $catId, int $rank, array $rejectedIDs){

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
        $affected = DB::table('posts')->where('id', $id)->update(['status' => 3, 'estimate_at' => $this->getTimeForDB()]);
        if($affected === 1){
            return ['success' => true, 'message' => 'deleted successfully post: '.$id];
        } else {
            return ['success' => false, 'message' => 'affected: '.$affected];
        }

    }

    private function rejectAuthors(string $tag) : array
    {
        $affected = DB::table('needle_authors')->where('author', $tag)->update(['processed' => 2]);
        if($affected !== 1) return ['success' => false, 'reason' => 'affected processed = 2 needle_authors = ' . $affected];
        if(!$this->clearTmpDir($tag)) return ['success' => false, 'reason' => 'failed to clear tmp files'];
        return ['success' => true];
    }

    private function loadAuthors()
    {
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

    private function loadQuery(string $author_tag) : array
    {
        try{
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

    private function getQuery() : array
    {

        // screen - это float соотношение сторон экрана у запросившего устройства. обычно от 0.4 до 1.8
        // в качестве дефолтного значения можно оставить 1 для квадратных изображений
        $postsChunkSize = 40;
        $screen = (float)$this->request['screen'] ?? 1;
        $offset = (int)$this->request['offset'] ?? 0;
        $requestedCategory = (string)$this->request['get'];


        $postsConfig = [
            'screen' => $screen,
            'offset' => $offset,
            'chunkSize' => $postsChunkSize,
        ];

        // получить список категорий отображение которых в данный момент валидно
        // для этого модель для таблицы категорий: получить список валидных категорий

        $category = new GalleryCategoryModel();
        $category = $category->getCategoryFromName($requestedCategory);

        if($category && $category->enabled){
            $this->logger->info("requested category $requestedCategory found in list of enabled");
            return GalleryPostAggregator::getPosts($category, $postsConfig);
        } else {
            $this->logger->info("requested category $requestedCategory not found or disabled");
            return [];
        }



    }

    private function estimateQuery() : array
    {

        $estimated_post = DB::select('SELECT status, tags_artist, original_uri FROM posts hp WHERE file_name = ?', [$this->request['post']]);
        $info = '';
        if(!empty($estimated_post)){
            try{
                $message = $this->insertRecordToHNA($estimated_post[0]->tags_artist, (int)$this->request['estimate']);
            } catch(Throwable $e){
                $info .= 'error insert record to hna occured: ' . $e->getMessage();
            }
            if((string)$estimated_post[0]->status !== (string)$this->request['estimate']){
                $affected =
                    DB::table('posts')
                    ->where('file_name', $this->request['post'])
                    ->update([
                        'status' => $this->request['estimate'],
                        'estimate_at' => $this->getTimeForDB()
                    ]);
                if($affected !== 1){
                    throw new Exception('estimateQuery affected:' . $affected. ' post: '.$this->request['post']);
                }
                if(!$info) $info = 'success without author record.';
                return ['success' => true, 'message' => $message, 'affected' => $affected, 'info' => $info];
            }
            return ['success' => true, 'message'=>'already estimated'];
        }
        throw new Exception('empty answer from select shown post');
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
        $url = 'test/autocomplete.php?q=' . $this->request['search'];
        $doc = new HtmlWeb();
        $json = $doc->load($url);
        $this->logger->info($json);
        return json_decode($json, true);
    }

    private function shownQuery() : array
    {
        try{
            $affected = DB::table('posts')->whereIn('file_name', $this->request['posts'])->update(['shown' => 1]);
            $this->logger->debug('IM HERE '.$this->request['posts']);
            foreach ($this->request['posts'] as $post){
                $this->logger->debug($post);
                $target = DB::select('SELECT tags_artist, tags_character FROM posts WHERE file_name = ?', [$post])[0];
                $this->logger->debug(json_encode($target));
                if(!$target->tags_artist){
                    continue;
                } else {
                    $artistCondition = array_map(function($item){return "'%$item%'";}, explode(' ', $target->tags_artist));
                    $artistCondition = 'tags_artist LIKE ' . implode(' OR tags_artist LIKE ', $artistCondition);
                }

                if(!$target->tags_character){
                    continue;
                } else {
                    $charCondition = array_map(function($item){return "'%$item%'";}, explode(' ', $target->tags_character));
                    $charCondition = 'tags_character LIKE ' . implode(' OR tags_character LIKE ', $charCondition);
                }


//                $similar_posts = DB::select("SELECT file_name FROM posts WHERE ($artistCondition) AND ($charCondition)");
//                $ih = new ImageHash();
//                $originHash = $ih->createHashFromFile(public_path('/img/'.$post), 15, 6, true);
//                $duplicates = [];
//                foreach($similar_posts as $similar){
//                    $similar = $similar->file_name;
//                    if($similar === $post) continue;
//                    $similarHash = $ih->createHashFromFile(public_path('/img/'.$similar), 15, 6, true);
//                    if($ih->compareImageHashes($originHash, $similarHash, 0.05)){
//                        $duplicates[] = $similar;
//                    }
//                }
//                $this->logger->debug('ORIGIN: '.$post);
//                $this->logger->debug('FIND DUPLICATES: '.json_encode($duplicates));
//                $affected = DB::table('posts')->whereIn('file_name', $duplicates)->update(['status' => 3, 'debug_mark' => "duplicate origin: $post"]);
//                $this->logger->debug("Success count: $affected");
//                if(count($duplicates) > 0){
//                    $html = "<img width='600px' src='https://mrclnn.com/img/$post'>";
//                    $html .= implode('', array_map(function($i){return "<img width='400px' src='https://mrclnn.com/img/$i'>";},$duplicates));
//
//                    $this->logger->debug($html);
//                }

            }

            return ['success' => true, 'affected' => $affected, 'message' => $affected . ' rows shown affected successfully.'];
        } catch (Throwable $e){
            return ['success' => false, 'reason' => $e->getMessage(), 'message' => $e->getMessage()];
        }


//        $answer = DB::select('SELECT shown FROM posts WHERE file_name = ?', [$this->request['post']]);
//        if(!empty($answer)){
//            if($answer[0]->shown === 0){
//                $affected = DB::table('posts')->where('file_name', $this->request['post'])->update(['shown' => 1]);
//                if($affected !== 1){
//                    throw new Exception('shownQuery affected:' . $affected. ' post: '.$this->request['post']);
////                    return ['success' => false, 'affected' => $affected];
////            $this->logger->debug($this->reque);
//                }
//                return ['success' => true,'affected' => $affected];
//            }
//            return ['success' => true, 'message'=>'already shown'];
//        }
//        throw new Exception('empty answer from select shown post');

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
