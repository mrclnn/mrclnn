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
    <title>Document</title>
</head>
<body>
@foreach($env['dupl'] as $dupl)
    <div class="container">
        <h4>duplicates:</h4>
        @foreach($dupl as $id => $src)
            <img src="/img/{{$src}}" alt="{{$id}}" width="200px">
        @endforeach
    </div>
@endforeach
<h1>all:</h1>
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

