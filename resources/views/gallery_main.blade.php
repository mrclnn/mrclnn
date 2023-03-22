<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="{{url('/css/galleryMain.css')}}">
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
    <script src="{{url('/js/galleryMain.js')}}"></script>

{{--    <script src="{{url('/js/libs/masonry.js')}}"></script>--}}

    <title>Gallery</title>

</head>
<body>
<h1>this is main gallery page</h1>
<div id="categories-container">
    @foreach($categories as $category)
    <div class="category" data-id="{{$category->id}}">
        <span class="category-name">{{$category->name}}</span>
        <input type="button" class="delete-category" value="delete category">
    </div>
    @endforeach
    <div id="add-category-frame">
        <input type="text" name="add-category-name" id="add-category-name" placeholder="name of category">
        <input type="text" name="tagSearch" id="tagSearch" placeholder="search tags">
        <span id="posts-count"></span>
        <ul id="matchedTags"></ul>
        <ul id="selected-tags"></ul>
        <input type="button" name="clear" id="clear" value="clear">
        <input type="button" name="add-category" id="add-category" value="add category">
    </div>
</div>
</body>
</html>
<?php
