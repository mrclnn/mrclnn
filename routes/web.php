<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/


use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::group(['namespace' => 'Gallery'], function(){

    Route::get('/gallery/post/{id}', 'GalleryPostController@get');

    Route::get('/gallery', 'GalleryMainController@execute');
    Route::get('/gallery/view', 'GalleryViewerController@execute');
    Route::get('/gallery/config', 'GalleryConfigController@execute');
    Route::get('/gallery/duplicates', 'DuplicatesController@execute');

    Route::post('/ajax', 'AjaxController@execute');

//    Route::post('/gallery/get', 'AjaxController@execute');
    Route::post('/gallery/get', 'AjaxController@get');
    Route::post('/gallery/shown', 'AjaxController@shown');
    Route::post('/gallery/estimate', 'AjaxController@estimate');

    Route::post('/gallery/delete-category', 'AjaxController@deleteCategory');
    Route::post('/gallery/recount-category', 'AjaxController@recountCategory');
    Route::post('/gallery/update-category', 'AjaxController@updateCategory');
    Route::post('/gallery/add-category', 'AjaxController@addCategory');
    Route::post('/gallery/check-category-count', 'AjaxController@checkCategoryCount');
    Route::post('/gallery/reject-duplicates', 'AjaxController@rejectDuplicates');
    Route::post('/gallery/approve-duplicates', 'AjaxController@approveDuplicates');

    Route::post('/gallery/search-tag', 'AjaxController@searchTag');




    Route::post('/gallery/search', 'AjaxController@search');
    Route::post('/gallery/load', 'AjaxController@load');
});
Route::group(['namespace' => 'Amo'], function(){
    Route::get('/amo', 'AmoController@amo');
});
Route::group(['namespace' => 'Utility'], function(){

    Route::get('/utility/json-to-class', 'MainController@jsonToClass');

});
Route::group(['namespace' => 'Telegram'], function(){

    Route::any('/tg/hook', 'Webhook@execute');
    Route::any('/tg/hookNew', 'WebhookNew@exec');

});
Route::group(['namespace' => 'Discord'], function(){

    Route::any('/discord/redirect', 'Webhook@execute');

});

Route::get('/', 'MainController@execute');

Route::any('/google/auth', 'GoogleApiController@execute');
Route::any('/dbg', 'dbg@execute');


Route::get('/filmlist', 'FilmListConroller@exec');

Route::get('/manager', 'ManagerController@execute');
Route::get('/notalone', 'Notalone@execute');
Route::get('/portfolio', 'PortfolioController@execute');


Route::get('/log', 'LogController@execute');
Route::get('/api-long-execute', 'TestController@longProcess');


//Route::get('/img/sb-admin-2/{path?}', function () {
//    if(\Auth::check()) {
//        // 21 = characters count of "templates/sb-admin-2"
//        $newPath = substr(ltrim($_SERVER['REQUEST_URI'], '/'), 21);
//        return \File::get(
//            public_path('xcscxcsx/' . $newPath)
//        );
//    }
//    return 'access denied';
//})->where(['path' => '.*']);






//Route::get('/products', 'MainController@execute');
Route::any('/test', 'TestController@execute');
//Route::get('/classifier', 'ClassifierController@execute');
//Route::get('/classifierEngine', 'ClassifierEngineController@execute');
//Route::get('/gallery/parser', 'GalleryParserController@execute');
//Route::get('/img', 'MainController@execute');
//Route::get('/calls', 'CallsController@execute');
//Route::get('/500', )
//Route::get('/monitor', 'MonitorController@execute');
Auth::routes();

Route::get('/home', 'HomeController@index')->name('home');
