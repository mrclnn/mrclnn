<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Document</title>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
    <script src="{{url('/js/libs/masonry.js')}}"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Montserrat:wght@300;700&display=swap');
        h1, h3{
            padding: 20px;
            font-size: 1.2em;
        }
        h2{
            margin: 150px 0;
            text-align: center;
        }
        body{
            font-family: Montserrat, sans-serif;
        }
        h1:hover,
        h3:hover{
            cursor: pointer;
            background-color: #ddd;
        }
        .hidden{
            display: none !important;
        }


        img{
            width: calc(50% - 4px);
            margin: 2px;
        }

        @media (min-width: 992px) {
            img{
                width: calc(20% - 20px);
                margin: 10px;
            }
        }
        .big{
            width: 900px;
            max-height: none;
            max-width: none;
            position: absolute;
            top : 10px;
            left : 10px;
        }
        .loader_container{
            display: flex;
            justify-content: center;
            align-items: center;
            height: 700px;
        }
        .circle-out {
            position:relative;
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: #ccc;
            overflow: hidden;
        }
        .circle-in {
            position: absolute;
            width: 96px;
            height: 96px;
            line-height: 96px;
            margin: 2px;
            border-radius: 50%;
            background: white;
            text-align: center;
        }
        .progress-circle {
            height: 110px;
            width: 220px;
            background: blue;
            position: absolute;
            top:-110px;
            left: -60px;
            transform: rotate(0deg);
            transform-origin: 110px 110px;
            transition: all .3s;
        }
    </style>
</head>
<body>
<div class="container">
    <a target="_blank" href="">go to category</a>
    <h1 class="cat" data-tag="">loading category...</h1>
    <div class="img_container hidden">
        <img src="" alt="" class="img">
        <img src="" alt="" class="img">
        <img src="" alt="" class="img">
        <img src="" alt="" class="img">
        <img src="" alt="" class="img">
        <img src="" alt="" class="img">
        <img src="" alt="" class="img">
        <img src="" alt="" class="img">
        <img src="" alt="" class="img">
        <img src="" alt="" class="img">
        <img src="" alt="" class="img">
        <img src="" alt="" class="img">
        <img src="" alt="" class="img">
        <img src="" alt="" class="img">
        <img src="" alt="" class="img">
        <img src="" alt="" class="img">
    </div>
    <div class="loader_container">
{{--        <h2 class="loading-indicator">loading...</h2>--}}
        <div class="circle-out">
            <div class="progress-circle"></div>
            <div class="circle-in"></div>
        </div>

    </div>
    <h3 class="reject" data-tag="">reject this artist</h3>
</div>
<script>
    window.onload = function(){
        core.init();
        core.loadTag();
    }

    var core = {
        gotocat : null,
        title : null,
        reject : null,
        masonry : null,
        container : null,
        imgList : null,
        imgContainer : null,
        loaderContainer : null,
        loaderIndicator : null,

        total : 0,
        current : 0,

        serverAnswer : [],

        init : function(){
            this.container = document.querySelector('div.container');
            this.imgContainer = this.container.querySelector('.img_container');
            this.loaderContainer = this.container.querySelector('.loader_container');
            this.loaderIndicator = this.container.querySelector('.loading-indicator');
            this.gotocat = this.container.querySelector('a');
            this.title = this.container.querySelector('.cat');
            this.reject = this.container.querySelector('.reject');
            this.imgList = this.container.querySelectorAll('.img');
            this.imgList.forEach(function(img){
                img.onload = function(){core.current++;console.log('img loaded'); core.onload()};
            });
        },

        hideContent : function(){
            this.imgContainer.classList.add('hidden');
            this.loaderContainer.classList.remove('hidden');
        },
        showContent : function(){
            this.imgContainer.classList.remove('hidden');
            this.loaderContainer.classList.add('hidden');
        },

        onload : function(){
            progress = Math.round(this.current / this.total * 100)
            // core.loaderIndicator.innerHTML = progress + '%';
            var progressEl = document.querySelector('.progress-circle');
            var crcl = document.querySelector('.circle-in');
            crcl.innerHTML = progress + '%';
            // console.log(progressEl);
            progressEl.style.transform = rot = 'rotate('+progress*1.8+'deg)';
            console.log(rot);

            if(this.current === this.total){
                // return;
                console.log('all loaded');
                this.current = 0;
                // core.loaderIndicator.innerHTML = 'loading...';

                core.gotocat.setAttribute('href', 'test.php?page=post&s=list&tags='+core.serverAnswer.tag);
                core.title.innerHTML = core.serverAnswer.tag+' ('+core.serverAnswer.count+') rest: '+core.serverAnswer.all;
                core.title.dataset.tag = core.serverAnswer.tag;
                core.reject.innerHTML = 'reject this artist';
                core.reject.dataset.tag = core.serverAnswer.tag;
                progressEl.style.transform = rot = 'rotate(0deg)';

                this.showContent();
                this.masonry = new Masonry(this.imgContainer, {
                    itemSelector: '.img'
                });
            }
        },
        loadTag : function(){
            this.hideContent();
            sendRequest('/ajax', {authors : true}, function(a){
                console.log(a);
                if(a && !a.failed){
                    if(a.empty){
                        document.body.innerHTML = '<h2>nothing here</h2';
                        return;
                    }
                    core.serverAnswer = a;
                    core.imgList.forEach(function(item, i){
                        item.setAttribute('src', core.serverAnswer.src[i]);
                    });
                    core.total = core.serverAnswer.src.length;
                    if(core.total > 16) core.total = 16;
                } else {
                    alert(a.reason);
                }
            })
        }
    }

    document.body.addEventListener('click', function(e){
        if(e.target.classList.contains('img')){
            e.target.classList.toggle('big');
        }
        if(e.target === core.title){
            tag = e.target.dataset.tag;
            if(!tag) {
                console.log('empty data-tag property');
                return;
            }
            e.target.classList.add('processed');
            e.target.innerHTML = 'Processed...';
            sendRequest('/ajax', {load : tag}, function(a){
                console.log(a);
                document.querySelector('.processed').innerHTML = 'Almost done...';
                if(a.success){
                    core.loadTag();
                } else {
                    alert(a.reason);
                }
            })
        }
        if(e.target.classList.contains('reject')){
            tag = e.target.dataset.tag;
            if(!tag){
                console.log('empty data-cat property try to reject...');
                return;
            }
            e.target.classList.add('processed');
            e.target.innerHTML = 'Processed...';
            sendRequest('/ajax', {reject_authors : tag}, function(a){
                console.log(a);
                if(!a.failed){
                    core.loadTag();
                } else {
                    alert(a.reason);
                }
            });
        }
    });

    function sendRequest(url, query, callback){
        query._token = $('meta[name="csrf-token"]').attr('content');

        $.ajax({
            type : 'POST',
            url : url,
            data : query,
            // processData : false,
            dataType : 'JSON',
            success : function(data){
                // console.log('success ajax lol');
                callback(data);
            },
            error : function(err){
                // console.log('error ajax lol');
                callback(err.responseText);
            }
        });
    }

</script>
</body>
</html><?php
