<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="{{url('/css/gallery.css?t=' . time())}}">
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
    <script src="{{url('/js/gallery.js?t=' . time())}}"></script>
    <title>mrclnn</title>

</head>
<body>
<h1>shud</h1>

<div id="controls">
    <h4 id="title">MEGUMIN<span id="titleCounter"></span></h4>
</div>
<div id="content">
    <ul id="menu">
        @foreach($categories as $category)
            <li class="category" data-value="{{$category->dir_name}}" data-count="{{$category->max}}" data-current="0">{{$category->name}}</li>
        @endforeach
    </ul>
    <img id="img" class="hidden" src="{{url('/img/loading.jpg')}}">
</div>
<div id="estimate">
    <img data-value="0" id="del" src="{{url('/img/delete.png')}}" alt>
    <img data-value="2" id="like" src="{{url('/img/dislike.png')}}" alt>
</div>
</body>
</html>
