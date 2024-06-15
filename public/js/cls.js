window.onload = function(){
    var input = document.getElementById('main-input');
    document.addEventListener('keyup', function(e){
        if(e.key === 'Enter' && e.target === input){
            var query = encodeURI(input.value);
            document.location.href = '/classifierEngine?param=' + query;
        }
    })
}


