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


use Illuminate\Support\Facades\Route;

Route::group(['namespace' => 'Gallery'], function(){
    Route::get('/gallery/view', 'GalleryViewerController@execute');
    Route::get('/gallery', 'GalleryMainController@execute');
    Route::post('/ajax', 'AjaxController@execute');
    Route::get('/gallery/config', 'GalleryConfigController@execute');
    Route::get('/duplicates', 'DuplicatesController@execute');
});

Route::get('/', 'MainController@execute');


Route::get('/filmlist', 'FilmListConroller@exec');

Route::get('/manager', 'ManagerController@execute');
Route::get('/notalone', 'Notalone@execute');
Route::get('/portfolio', 'PortfolioController@execute');


Route::get('/log', 'LogController@execute');


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
//Route::get('/test', 'TestController@execute');
//Route::get('/classifier', 'ClassifierController@execute');
//Route::get('/classifierEngine', 'ClassifierEngineController@execute');
//Route::get('/gallery/parser', 'GalleryParserController@execute');
//Route::get('/img', 'MainController@execute');
//Route::get('/calls', 'CallsController@execute');
//Route::get('/500', )
//Route::get('/monitor', 'MonitorController@execute');