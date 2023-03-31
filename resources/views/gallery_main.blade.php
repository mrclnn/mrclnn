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
<h1>Tags config</h1>
<div id="tags-config" class="main-frame">
    <input type="text" name="search-tag" id="search-tag" placeholder="search tag">
    <ul id="selected-tags"></ul>
    <span id="tag-alias"></span>
    <input type="text" name="search-alias" id="search-alias">
</div>
<h1>Categories config</h1>
<div id="add-category-frame" class="main-frame">
    <h2 class="main-frame-title">Add new category</h2>
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
    <input type="button" name="add-category" id="add-category" value="add category" data-target="create">
</div>
<div id="categories-container" class="main-frame">
    <h2 class="main-frame-title">Categories list</h2>
    @if(count($categories) > 0)
        @foreach($categories as $category)
            <div class="category main-frame" data-id="{{$category->id}}">
                <span class="category-count">{{$category->count}}</span>
                <span class="category-name">{{$category->name}}</span>
                <ul class="extend-tags hidden">
                    @foreach($category->extendTags as $tag)
                        <li class="selectedTag" data-id="{{$tag->id}}">{{$tag->tag}}</li>
                    @endforeach
                </ul>
                <ul class="exclude-tags hidden">
                    @foreach($category->excludeTags as $tag)
                        <li class="selectedTag" data-id="{{$tag->id}}">{{$tag->tag}}</li>
                    @endforeach
                </ul>
                <ul class="include-tags hidden">
                    @foreach($category->includeTags as $tag)
                        <li class="selectedTag" data-id="{{$tag->id}}">{{$tag->tag}}</li>
                    @endforeach
                </ul>
                <input type="button" class="edit-category" value="edit category">
                <input type="button" class="update-category" value="recount category">
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
