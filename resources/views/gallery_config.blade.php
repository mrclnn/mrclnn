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
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Montserrat:wght@300;700&display=swap');
        .big{
            /*position: fixed;*/
            /*top: 0;*/
            /*bottom: 0;*/
            /*height: 100%;*/
        }
        body{
            background-color: #222;
            color: #fff;
            font-family: Montserrat, sans-serif;
        }
        .head{
            width: 100%;
        }
        span[data-rank]{
            padding: 10px;
            font-size: 30px;
        }
        span[data-rank]:hover{
            cursor: pointer;
            background-color: #eee;
        }
        span[data-rank].selectedRank{
            background-color: #ddd;
        }
        h3{
            text-align: center;
            padding: 20px;
        }
        h3:hover{
            cursor: pointer;

            background-color: #eee;
        }
        .hidden{
            display: none !important;
        }
        #estimate{
            position: absolute;
            display: flex;
            flex-direction: column;
            z-index: 1;
            background-color: rgba(0, 0, 0, 0.7);
        }
        h2{
            flex: 1;
            text-align: center;
            vertical-align: center;
            /*display: table;*/
            color: #fff;
            opacity: 1;
        }
        h2:hover{
            background-color: rgba(255, 255, 255, 0.7);
            cursor: pointer;
        }
        img{
            width: calc(50% - 4px);
            margin: 2px;
            box-sizing: border-box;
            border: 5px solid #de1313;
        }

        @media (min-width: 992px) {
            img{
                width: calc(20% - 20px);
                margin: 10px;
            }
        }
        /*.rejected{*/
        /*    border: 5px solid #de1313;*/
        /*}*/
        .approved{
            border: 5px solid #15b415 !important;
        }
        .progressBar{
            height: 30px;
            background-color: #555;
            border-radius: 15px;
        }
        .progressBar div{
            background-color: #ccc;
            height: 30px;
            transition: .5s;
            border-radius: 15px;
            padding-left: 20px;
            line-height: 30px;
            box-sizing: border-box;
        }
        .unselectable {
            -webkit-touch-callout: none; /* iOS Safari */
            -webkit-user-select: none;   /* Chrome/Safari/Opera */
            -khtml-user-select: none;    /* Konqueror */
            -moz-user-select: none;      /* Firefox */
            -ms-user-select: none;       /* Internet Explorer/Edge */
            user-select: none;           /* Non-prefixed version, currently
                                  not supported by any browser */
        }
        #submit{
            padding: 20px;
        }


    </style>
</head>
<body>

<div id="container">
    <div class="head">
        <h1 data-id=""></h1>
        <div class="rank">
            <span data-rank="1" class="unselectable">S</span>
            <span data-rank="2" class="unselectable">A</span>
            <span data-rank="3" class="unselectable">B</span>
            <span data-rank="4" class="unselectable">C</span>
            <span data-rank="5" class="unselectable">D</span>
            <span data-rank="9" class="unselectable">skip</span>
        </div>
    </div>
    <h2 id="submit" class="unselectable">submit</h2>
</div>
<div class="img-container"></div>
{{--<h3>submit</h3>--}}
{{--<div id="estimate" class="hidden" data-target="">--}}
{{--    <h2 id="skip">SKIP</h2>--}}
{{--    <h2>FULLSCREEN</h2>--}}
{{--    <h2 id="dupl">DUPLICATE</h2>--}}
{{--</div>--}}

<script>

    submit.onclick = function(){
        var selectedRank = document.querySelector('.selectedRank');
        if(!selectedRank) {
            alert('you should choose rank first!'); return
        }
        selectedRank = selectedRank.dataset.rank;

        var posts = document.querySelectorAll('img:not(.approved)');
        var rejectedIDs = [];
        posts.forEach(function(item){
            rejectedIDs.push(item.dataset.id);
        })
        sendRequest('/ajax', {setRank : core.data.tagId, rank : selectedRank, rejectedIDs : rejectedIDs}, function(answ){
            console.log(answ);
            if (answ.success){
                document.location.reload();
            }

        });

    }
    // var offset = 0;
    // getPosts(offset);
    var core = {
        ui : {
            title : null,
            imgContainer : null,
            current : 0,
            blockScroll : false,
            currentChunk : 0,
            progressBar : null,
            content : [],
            init : function(){
                this.title = document.querySelector('h1');
                this.imgContainer = document.querySelector('.img-container');
                this.progressBar = function(){
                    progressBar = document.createElement('div');
                    progressBar.className = 'progressBar';
                    progressBar.innerHTML = '<div></div>'
                    progressBar.setValue = function(val){
                        this.firstElementChild.innerText = val + '%';
                        this.firstElementChild.style.width = val + '%';
                    }
                    return progressBar;
                }();


                window.addEventListener('scroll', function() {
                    if(!core.ui.blockScroll && (document.body.offsetHeight - (window.pageYOffset + document.documentElement.clientHeight) < document.documentElement.clientHeight * 2)){
                        core.ui.blockScroll = true;
                        core.ui.renderImagesChunk();
                    }
                });
            },
            onload : null,
            renderCategory : function(){
                this.imgContainer.innerHTML = '';
                this.title.innerHTML = core.data.tagName;
                this.title.dataset.id = core.data.tagId;
                core.ui.current = 0;
                this.renderImagesChunk();

            },
            renderImagesChunk : function(){
                var chunkSize = 8;

                core.data.currentChunkCount = function(){
                    var count = 0;
                    for (var char = core.ui.currentChunk; char < core.ui.currentChunk + chunkSize; char++){
                        ch = core.data.duplicates[char];
                        if(!ch) break;
                        count += Object.keys('ch').length;
                    }
                    return count;
                }();
                this.imgContainer.insertAdjacentElement('beforeend', core.ui.progressBar);
                for (var char = core.ui.currentChunk; char < core.ui.currentChunk + chunkSize; char++){
                    ch = core.data.duplicates[char];
                    if(!ch) break;
                    var dupl = document.createElement('div');
                    dupl.classList.add('dupl');
                    dupl.innerHTML = '<p>duplicates:</p>';
                    core.ui.content.push(dupl);
                    for(id in ch){
                        if(!ch.hasOwnProperty(id)) continue;
                        var img = getImg('https://mrclnn.com/img/'+ch[id].src, id);
                        dupl.insertAdjacentElement('beforeend', img);
                    }
                }

                core.ui.currentChunk += chunkSize;

                function getImg(src, id){
                    var img = new Image();
                    img.setAttribute('data-id', id);
                    img.className = 'img';
                    img.addEventListener('load', function(e){
                        progress = Math.round(++core.ui.current / core.data.currentChunkCount * 100);
                        console.log(progress);
                        core.ui.progressBar.setValue(progress);
                        if(progress === 100){
                            setTimeout(function(){
                                core.ui.progressBar.remove();
                                core.ui.content.forEach(function(dupl){
                                    core.ui.imgContainer.insertAdjacentElement('beforeend', dupl);
                                });
                                core.ui.content = [];
                                core.ui.current = 0;
                                core.ui.blockScroll = false;
                            }, 1500);
                        }
                    });
                    img.addEventListener('click', function(e){
                        e.target.classList.toggle('approved');
                    });
                    img.src = src;
                    return img;
                }

            }
        },
        loadCategory : function(){
            sendRequest('/ajax', {duplicates : true}, function(answer){
                if(answer.success){

                    core.data.tagName = answer.env.tag_name;
                    core.data.tagId = answer.env.tag_id;
                    core.data.duplicates = answer.env.dupl;
                    core.ui.renderCategory();
                    // console.log(answer);
                    // return;
                }
            });
        },
        data : {
            tagName : null,
            tagId : null,
            duplicates : null,
            currentChunkCount : 0
        },
        load : function(){
            // var connection = new EventSource('/duplicates');
            // connection.onopen = function(e) {
            //     console.log("открыто");
            // };
            // // eventSource.addEventListener('end',function);
            // connection.onerror = function(e) {
            //     console.log("Событие: error");
            //     console.log(e);
            //     connection.close();
            //     // if (this.readyState === EventSource.CONNECTING) {
            //     //     console.log(`Переподключение (readyState=${this.readyState})...`);
            //     // } else {
            //     //     console.log("Произошла ошибка.");
            //     // }
            // };
            // connection.onmessage = function(e) {
            //     // message = JSON.parse(e.data);
            //     if(false || e.action === 'end'){
            //         console.log('end');
            //         connection.close();
            //     } else {
            //         console.log(e.data);
            //     }
            //
            // };
        }
    }

    core.ui.init();
    core.loadCategory();
    // core.load();

    document.body.addEventListener('click', function(e){

        if(e.target.dataset.rank){
            document.querySelectorAll('span.selectedRank').forEach(function(item){
                item.classList.remove('selectedRank');
            });
            e.target.classList.add('selectedRank');
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
</html>