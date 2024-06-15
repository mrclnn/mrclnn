<?php
try{
//    dd($env);


    ?>
        <!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Document</title>
    <link rel="stylesheet" href="{{url('/css/duplicates.css')}}">
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
    <script src="{{url('/js/duplicates.js')}}"></script>
</head>
<body>
<h1>{{$tag}}</h1>

<input type="button" value="send" id="send-duplicates">
@foreach($characters as $characterName => $posts)
    <div class="container">
        <h4>{{$characterName}}:</h4>
        @foreach($posts as $post)
            <img
                src="/gallery/post/{{$post->post_id}}"
                alt="{{$post->id}}"
                width="{{$post->height/$post->width > 1 ? '16%' : '32%'}}"
            >
        @endforeach
    </div>
@endforeach

<button id="approve">approve cleared and go next</button>
{{--@foreach($env['all'] as $post)--}}
{{--    <img src="/img/{{$post->file_name}}" alt="" width="200px">--}}
{{--@endforeach--}}
</body>
</html>
<?php
}catch(Throwable $e){
    echo $e->getMessage();
    echo '<br>at line<br>';
    echo $e->getLine();
}

