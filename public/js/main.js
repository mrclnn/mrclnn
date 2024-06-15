window.onload = function(){
    var navItems = document.querySelectorAll('.nav_list');
    var gridContainer = document.querySelector('#product_container');
    var masonry = new Masonry(gridContainer, {
        itemSelector: '.grid-item',
        // columnWidth: 200
    })
    navItems.forEach(function(item){
        item.addEventListener('click', function(e){
            if(e.target.classList.contains('nav_item')){
                e.target.firstElementChild.click();
            }
        })
    })
}