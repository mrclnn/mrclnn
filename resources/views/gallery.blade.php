<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="mobile-web-app-capable" content="yes">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="{{url('/css/gallery.css?t=' . time())}}">
{{--    <link rel="preload" href="{{url('/img/like.png}}">--}}
{{--    <link rel="preload" href="{{url('/img/dislike.png}}">--}}
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
    <script src="{{url('/js/gallery.js?t=' . time())}}"></script>
    <title>mrclnn</title>

</head>
<body>
<div id="controls" class="fullscreen-controlls">
    <div class="title">
        <h4 id="title">welcom</h4>
{{--        <span id="title_counter"></span>--}}
        <img id="slideshow" data-mode="off" class="button" src="{{url('/img/forward.png?t')}}" alt="">
        <img id="fullscreen" class="button" src="{{url('/img/fullscreen-enable.png?t')}}" alt="">
    </div>
    <div class="search">
        <input type="text" id="search" placeholder="search..." autocomplete="off">
    </div>
    <ul id="menu">
        @foreach($categories as $category)
            <li class="category" data-value="{{$category->name}}" data-tag="{{$category->name}}">
                <span class="category-name">{{$category->name}}</span>
                <span class="category_count">1</span>
            </li>
        @endforeach
        <ul id="from-remote"></ul>
    </ul>
</div>
<div id="content">
    <canvas id="cnv"></canvas>
</div>
<div class="info hidden">
{{--    <div class="show-author info-button"></div>--}}
{{--    <div class="show-info info-button"></div>--}}
    <div id="title_counter"></div>
    <div class="slideshow-button"></div>
    <div class="pic-info">
        <ul class="pic-fan-list filter-list">
            <li></li>
        </ul>
{{--        <p>author(s):</p>--}}
{{--        <p>character(s):</p>--}}
        <ul class="pic-char-list filter-list">
            <li></li>
        </ul>
{{--        <p>fandom(s):</p>--}}
        <ul class="pic-author-list filter-list">
            <li></li>
        </ul>
    </div>
</div>
{{--<div id="info" class="hidden"></div>--}}
<img src="/img/like.png" alt="" id="mask">
</body>
</html>
