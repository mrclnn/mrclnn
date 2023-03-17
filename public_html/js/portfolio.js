console.log('here');
window.onload = function(){
    var viewer = document.querySelector('model-viewer');
    document.getElementById('other-works').addEventListener('click', function(e){
        // viewer.setProperty('src', item.getProperty('src'));
        var newSrc = e.target.getAttribute('src').replace('/covers', '').replace('.jpg', '.glb');

        document.querySelector('#description h1').innerHTML = e.target.dataset.name;
        document.querySelector('#description p').innerHTML = e.target.dataset.desc;

        viewer.setAttribute('src', newSrc);
        document.querySelector('img.chosen').classList.remove('chosen');
        e.target.classList.add('chosen');
    })
}