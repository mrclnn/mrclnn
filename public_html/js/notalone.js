console.log('e');
window.onload = function(){
    var player = {
        node: null,
        init: function(){
            this.node = document.querySelector('video');
            this.node.onclick = function(){
                if(player.node.paused){
                    player.play();
                } else {
                    player.pause();
                }
            }
            setInterval(function(){
                sendRequest('/ajax', {notalone:true,sync:true}, function(answ){
                    console.log(answ);
                    if(answ.paused && !player.node.paused){
                        player.node.currentTime = answ.time;
                        player.node.pause();
                    }
                    if(!answ.paused && player.node.paused) player.node.play();
                });
            },500);
        },
        play: function(){
            // this.node.play();
            sendRequest('/ajax', {notalone:true,setPlay:true}, function(answ){});
        },
        pause: function(){
            sendRequest('/ajax', {notalone:true,setPause:true, time:this.node.currentTime}, function(answ){});
            // this.node.pause();
        }
    }
    player.init();
    console.log(player.node);




// player.duration
// player.currentTime
//
//     sendRequest('/ajax', {notalone:true,sync:true}, function(answ){
//         console.log(answ);
//     })

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
}
