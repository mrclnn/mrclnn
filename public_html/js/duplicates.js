let app = {
    mode : false,
    init : function(){
        document.addEventListener('mousedown', function(event){
            event.preventDefault();
            // console.log(event.target.tagName);
            if(event.target.tagName !== 'IMG') return;
            app.mode = true;
            this.processImg(event.target);

        }.bind(app));
        document.addEventListener('mouseup', function(){app.mode = false});
        document.querySelectorAll('img').forEach(function(img){
            img.onmouseenter = function(event){
                if(!app.mode) return;
                console.log('enter');
                app.processImg(event.target);
            }
        })
        let sendButton = document.querySelector('#send-duplicates');
        sendButton.addEventListener('click', app.sendDuplicates);
    },
    processImg : function(img){
        img.classList.toggle('duplicate');
    },
    sendDuplicates : function(){
        let duplicates = document.querySelectorAll('.duplicate');
        let duplicatesID = [];
        duplicates.forEach(function(duplicate){
            let id = duplicate.getAttribute('alt');
            duplicatesID.push(id);
        })

        console.log(duplicatesID);

        app.sendRequest('/ajax', {duplicates : duplicatesID}, function(answer){
            console.log(answer);
            location.reload();
        });
    },
    sendRequest : function(url, query, callback){
        query._token = $('meta[name="csrf-token"]').attr('content');

        $.ajax({
            type: 'POST',
            url: url,
            data: query,
            // processData : false,
            dataType: 'JSON',
            success: function (data) {
                // console.log('success ajax lol');
                callback(data);
            },
            error: function (err) {
                // console.log('error ajax lol');
                callback(err.responseText);
            }
        })
    },
}
window.addEventListener('load', app.init);


