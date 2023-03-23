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
<div id="add-category-frame">
    <input type="text" name="add-category-name" id="add-category-name" placeholder="name of category">

    <input type="text" class="search-tag" id="extend-search" placeholder="EXTEND TAGS">
    <input type="text" class="search-tag" id="exclude-search" placeholder="EXCLUDE TAGS">
    <input type="text" class="search-tag" id="include-search" placeholder="INCLUDE TAGS">

    <span id="posts-count"></span>
    <ul id="matched-tags"></ul>

    <ul class="selected-tags" id="extended-tags"></ul>
    <ul class="selected-tags" id="excluded-tags"></ul>
    <ul class="selected-tags" id="included-tags"></ul>

    <input type="button" name="clear" id="clear" value="clear">
    <input type="button" name="add-category" id="add-category" value="add category">
</div>
<div id="categories-container">

    @if(count($categories) > 0)
        @foreach($categories as $category)
            <div class="category" data-id="{{$category->id}}">
                <span class="category-name">{{$category->name}}</span>
                <input type="button" class="delete-category" value="delete category">
            </div>
        @endforeach
    @else
        <h3>not found categories</h3>
    @endif
</div>
</body>
</html>
<?php
