// We listen to the resize event
// window.addEventListener('resize', () => {
//     // We execute the same script as before
//     let vh = window.innerHeight * 0.01;
//     document.documentElement.style.setProperty('--vh', vh+'px');
// });
window.onload = function(){
    app.UI.init();


    let fromRemote = document.querySelector('#from-remote');
    fromRemote.onclick = function(e){
        // console.log(e.target);
        tagName = e.target.closest('.category').querySelector('.category-name').innerText;
        // console.log(tag);
        result = confirm('Load category ' + tagName + '?');
        if(result){
            sendRequest('/gallery/load', {tag : tagName}, function(answ){
                console.log(answ);
            });
        }
    }
    fsc = false;

    var categories = app.data.categories;
    search.oninput = function(e){
        // menu.querySelectorAll('.category:not(.hidden)')
        var searchWord = e.target.value.toLowerCase();
        for(tag in categories){
            item = categories[tag];
            if(item.innerText.trim().toLowerCase().indexOf(searchWord) === -1){
                item.classList.add('hidden');
            } else {
                item.classList.remove('hidden');
            }
        }
        if(searchWord.length < 4) return;
        sendRequest('/gallery/search', {word : searchWord}, function(answ){
            var catsFromRemote = '';
            if(answ.length > 0){
                catsFromRemote += '<li class="info">uploaded from remote:</li>'
                answ.forEach(function(item){
                    var tag = item.value;
                    var count = item.label.replaceAll(/\D/g, '');
                    catsFromRemote += '<li class="category"><span class="category-name">'+tag+'</span><span class="category-count">'+count+'</span></li>';
                });

            }
            fromRemote.innerHTML = catsFromRemote;
        });
    }





}

let app = {
    info : function(){
        this.slider.currentPic.printTags();
    },
    UI : {
        DOM : {
            controllsContainer : null,
            contentContainer : null,
            menuList : null,
            mainTitle : null,
            searchInput : null,
            fullscreenButton : null,
            infoBar : null,
            canvas : null,
            mask : null,
            info : null,
            pic_info : null,
            pic_artist_list : null,
            pic_fan_list : null,
            pic_char_list : null,
            slideshowButton : null,
            progressBar : null,
            init : function(){
                this.controllsContainer = document.querySelector('#controls');
                this.slideshowButton = this.controllsContainer.querySelector('#slideshow');
                this.fullscreenButton = this.controllsContainer.querySelector('#fullscreen');
                this.contentContainer = document.querySelector('#content');
                this.progressBar = document.querySelector('#progress-bar');
                this.menuList = document.querySelector('#menu');
                this.mainTitle = document.querySelector('#title');
                this.searchInput = document.querySelector('#search');
                this.infoBar = document.querySelector('#info');
                this.canvas = document.querySelector('#cnv');
                this.mask = document.querySelector('#mask');
                this.info = document.querySelector('.info');
                this.pic_info = this.info.querySelector('.pic-info');
                this.pic_artist_list = this.pic_info.querySelector('.pic-author-list');
                this.pic_fan_list = this.pic_info.querySelector('.pic-fan-list');
                this.pic_char_list = this.pic_info.querySelector('.pic-char-list');
                this.mask.setSize = function(){
                    if(app.UI.DOM.contentContainer.clientHeight > app.UI.DOM.contentContainer.clientWidth){
                        this.style.width = '20%';
                        this.style.height = '';
                    } else {
                        this.style.height = '20%';
                        this.style.width = '';
                    }
                }
                this.mask.setSize();

                this.pic_info.addEventListener('click', function(e){
                    // this.toggleBar();
                    // console.log(slider.currentPic);
                    // console.log(e.target.tag);
                    if(e.target.classList.contains('pic-filter')){
                        filter = e.target.innerHTML;

                        // console.log(filter);
                        // return;

                        slider.setCategory(filter);
                    }
                })

                window.addEventListener('resize', function(){
                    app.UI.canvas.init();
                    app.slider.currentPic.render();
                    app.UI.DOM.mask.setSize();
                    if(app.slider.currentCat){
                        // реинициализирует категорию с новым запросом ширины экрана
                        // если делать без таймаута может быть некорректные размеры экрана, т.к. телефон может пролагать
                        setTimeout(function(){
                            app.slider.setCategory(app.slider.currentCat, true);
                        }, 500);
                    }
                });
                document.addEventListener('keyup', function(e){
                    // console.log(e.key);
                    switch (e.key) {
                        case 'f':
                            app.UI.toggleFullscreen();
                            break;
                        case ' ':
                            app.slider.slideshow.toggle();
                            break;

                        case 'ArrowRight':
                            app.slider.next();
                            break;
                        case 'ArrowLeft':
                            app.slider.prev();
                            break;

                        case 'ArrowDown':
                            // console.log('delete');
                            app.slider.delete();
                            break;
                        case 'ArrowUp':
                            // console.log('like');
                            app.slider.like();
                            break;
                    }
                });
                document.querySelectorAll('.category').forEach(function(item){
                    app.data.categories[item.dataset.tag] = item;
                });

                this.contentContainer.addEventListener('click', function(){
                    if(app.UI.DOM.canvas.classList.contains('hidden')) return;
                    app.UI.toggleBar();
                    app.slider.sendViewed();
                });
                this.contentContainer.addEventListener('touchstart', function(e){
                    if(e.target.id !== 'cnv') return;
                    app.UI.swipe.start(e.touches[0].screenX, e.touches[0].screenY);
                });
                this.contentContainer.addEventListener('touchmove', function(e){
                    if(e.target.id !== 'cnv') return;
                    e.preventDefault();
                    app.UI.swipe.process(e.changedTouches[0].screenX, e.changedTouches[0].screenY);
                });
                this.contentContainer.addEventListener('touchend', function(e){
                    if(e.target.id !== 'cnv') return;
                    app.UI.swipe.end();
                });

                app.UI.swipe.yF = function(y){
                    if(y>0){
                        app.UI.DOM.mask.setAttribute('src', '/img/delete.png?t');
                    } else {
                        app.UI.DOM.mask.setAttribute('src', '/img/like.png?t');
                    }
                    opacity = Math.abs(y)>100 ? 100 : Math.abs(y);
                    if(opacity < 10) opacity = 0;
                    app.UI.DOM.mask.style.opacity = opacity / 100;
                    app.UI.DOM.canvas.style.filter = 'brightness('+(100-opacity/2)+'%)';
                    // img.style.filter = 'grayscale('+opacity+'%)';
                }
                app.UI.swipe.xF = function(x){
                    if(x>10) {
                        app.slider.prev();
                        app.UI.swipe.blockProcess = true;
                    }
                    if(x<-10) {
                        app.slider.next();
                        app.UI.swipe.blockProcess = true;
                    }
                }
                app.UI.swipe.endF = function(dir, val){
                    if(dir === 'y'){
                        if(val>50){
                            app.slider.delete(function(){
                                app.UI.DOM.mask.style.opacity = 0;
                                app.UI.DOM.canvas.style.filter = 'brightness(100%)';
                            });
                        } else if(val<-50) {
                            app.slider.like();
                            setTimeout(function(){
                                app.UI.DOM.mask.style.opacity = 0;
                                app.UI.DOM.canvas.style.filter = 'brightness(100%)';
                            }, 400);
                        } else {
                            app.UI.DOM.mask.style.opacity = 0;
                            app.UI.DOM.canvas.style.filter = 'brightness(100%)';
                        }
                    }
                }

                this.controllsContainer.onclick = function(e){
                    // console.log(e.target);
                    if(e.target.id === 'title'){
                        app.UI.toggleMenu();
                        return;
                    }
                    if(e.target.closest('#search')) return;
                    if(e.target.closest('.category')){
                        app.UI.hideMenu();
                        app.slider.setCategory(e.target.closest('.category'));
                    }

                }
                this.fullscreenButton.onclick = function(e){
                    app.UI.toggleFullscreen();
                }
                this.slideshowButton.onclick = function(e){
                    app.slider.slideshow.toggle();
                }
            }
        },
        canvas : {
            ctx : null,
            init : function(){
                var cnv = app.UI.DOM.canvas;
                var cnt = app.UI.DOM.contentContainer;
                if(cnv === null || cnt === null) {
                    alert('failed to get canvas or content container');
                    return;
                }
                cntW = cnt.clientWidth;
                cntH = cnt.clientHeight;

                cnv.setAttribute('width', cntW * 2);
                cnv.setAttribute('height', cntH * 2);
                cnv.style.width = cntW + 'px';
                cnv.style.height = cntH + 'px';

                this.ctx = cnv.getContext('2d');
            },
            clear : function(){
                cnv = app.UI.DOM.canvas;
                this.ctx.clearRect(0,0, cnv.width, cnv.height);
            },
            render : function(pic){
                cnv = app.UI.DOM.canvas;
                var containerH = cnv.height;
                var containerW = cnv.width;

                var imgH = (pic instanceof Pic) ? pic.h : pic.height;
                var imgW = (pic instanceof Pic) ? pic.w : pic.width;
                var img = (pic instanceof Pic) ? pic.img : pic;

                var finalH = containerH;
                var finalW = (finalH * imgW) / imgH;
                if(finalW > containerW){
                    finalW = containerW;
                    finalH = (finalW * imgH) / imgW;
                }

                var offsetX = (containerW - finalW) / 2;
                var offsetY = (containerH - finalH) / 2;

                this.ctx.drawImage(img, offsetX, offsetY, finalW, finalH);
                this.ctx.save();
                var filter = 'blur(4px)';
                if(offsetX === 0){
                    // horizontal
                    reflection = offsetY/4;

                    this.ctx.translate(0, offsetY);
                    this.ctx.scale(1, -1);
                    this.ctx.filter = filter;
                    this.ctx.drawImage(img, 0, 0, img.width, reflection, 0, 0, finalW, offsetY);
                    this.ctx.restore();
                    this.ctx.save();

                    this.ctx.translate(0, offsetY + finalH);
                    this.ctx.scale(1, -1);
                    this.ctx.filter = filter;
                    this.ctx.drawImage(img, 0, img.height - reflection, img.width, reflection, 0, -offsetY, finalW, offsetY);
                    this.ctx.restore();
                    this.ctx.save();
                } else {
                    //vertical
                    reflection = offsetX/4;

                    this.ctx.translate(0, 0);
                    this.ctx.scale(-1, 1);
                    this.ctx.filter = filter;
                    this.ctx.drawImage(img, 0, 0, reflection, img.height, -offsetX, 0, offsetX, finalH);
                    this.ctx.restore();
                    this.ctx.save();

                    this.ctx.translate(offsetX + finalW, 0);
                    this.ctx.scale(-1, 1);
                    this.ctx.filter = filter;
                    this.ctx.drawImage(img, img.width - reflection, 0, reflection, img.height, -offsetX, 0, offsetX, finalH);
                    this.ctx.restore();
                    this.ctx.save();
                }
            }
        },
        swipe : {
            blockProcess : false,
            x : null,
            y : null,
            startX : null,
            startY : null,
            direction : null,
            directionValue : 0,
            _xF : [],
            _yF : [],
            _endF : [],
            start : function(x, y){
                this.blockProcess = false;
                this.x = x;
                this.startX = x;
                this.y = y;
                this.startY = y;
                this.direction = null;
                this.directionValue = 0;
            },
            process : function(x, y){
                if(this.blockProcess) return;
                if(cnv.classList.contains('hidden')) return;
                var cords = {'x' : x, 'y' : y};

                if(Math.abs(this.x - x) > Math.abs(this.y - y)){
                    this.direction = this.direction || 'x';
                } else {
                    this.direction = this.direction || 'y';
                }
                direction = this.direction;
                this.directionValue = cords[direction] - this['start'+direction.toUpperCase()];
                this[direction+'Move'](this.directionValue);
                this[direction] = cords[direction];
            },
            end : function(){
                this._endF.forEach(function(item){
                    item(app.UI.swipe.direction, app.UI.swipe.directionValue);
                });
            },
            xMove : function(x){
                this._xF.forEach(function(item){
                    item(x);
                });
            },
            yMove : function(y){
                this._yF.forEach(function(item){
                    item(y);
                });
            },
            set xF(func){
                if(typeof func === 'function'){
                    this._xF.push(func);
                }
            },
            set yF(func){
                if(typeof func === 'function'){
                    this._yF.push(func);
                }
            },
            set endF(func){
                if(typeof func === 'function'){
                    this._endF.push(func);
                }
            }
        },
        state : {
            fullscreenMode : false,
        },
        init : function(){

            this.DOM.init();
            this.canvas.init();

        },
        toggleMenu : function(){
            if(this.DOM.controllsContainer.classList.contains('fullscreen-controlls')){
                this.hideMenu();
            } else {
                this.showMenu();
            }
        },
        showMenu : function(){
            this.DOM.controllsContainer.classList.remove('hidden');
            this.DOM.controllsContainer.classList.add('fullscreen-controlls');
            this.DOM.controllsContainer.classList.add('hidden');

            this.DOM.searchInput.classList.remove('hidden');
            this.DOM.fullscreenButton.classList.remove('hidden');
            this.DOM.menuList.classList.remove('hidden');
        },
        hideMenu : function(){
            this.DOM.controllsContainer.classList.add('hidden');
            this.DOM.info.classList.add('hidden');
            this.DOM.controllsContainer.classList.remove('fullscreen-controlls');

            this.DOM.searchInput.classList.add('hidden');
            this.DOM.fullscreenButton.classList.add('hidden');
            this.DOM.menuList.classList.add('hidden');
        },
        toggleBar : function(){
            this.DOM.controllsContainer.classList.toggle('hidden');
            this.DOM.info.classList.toggle('hidden');
        },
        toggleFullscreen : function(){

            if(this.state.fullscreenMode){
                this.closeFullscreen();
            } else {
                app.UI.openFullscreen();
            }

        },
        openFullscreen : function(){
            if (document.body.requestFullscreen) {
                document.body.requestFullscreen({ navigationUI: 'hide' });
            } else if (document.webkitRequestFullscreen) { /* Safari */
                document.body.webkitRequestFullscreen({ navigationUI: 'hide' });
            } else if (content.msRequestFullscreen) { /* IE11 */
                document.body.msRequestFullscreen({ navigationUI: 'hide' });
            }
            this.DOM.fullscreenButton.setAttribute('src', '/img/full-screen-icon.png?t');
            this.state.fullscreenMode = true;
        },
        closeFullscreen : function(){
            if (document.exitFullscreen) {
                document.exitFullscreen();
            } else if (document.webkitExitFullscreen) { /* Safari */
                document.webkitExitFullscreen();
            } else if (document.msExitFullscreen) { /* IE11 */
                document.msExitFullscreen();
            }
            this.DOM.fullscreenButton.setAttribute('src', '/img/full-screen-icon.png?t');
            this.state.fullscreenMode = false;
        }
    },
    slider : {
        i : 0,
        max : 0,
        categoryCount : 0,
        currentPic : {},
        currentCat : '',
        pics : [], // pic
        cashed : {},
        viewed : [],
        categoryLoading : false,
        sendViewed : function(){
            if(this.viewed.length === 0) return;
            sendRequest('/gallery/shown',{posts : this.viewed}, function(answ){
                // app.logger.info(answ.message);
            });
            this.viewed = [];
        },
        delete : function(callback){
            //todo hardcoded estimate status
            sendRequest('/gallery/estimate',{status : 0, post : this.currentPic.id}, function(answ){
                if(answ.success){
                    if(callback && typeof callback === 'function'){
                        callback();
                    }
                    app.logger.info('deleted');
                    app.slider.slideshow.refresh();
                    slider.pics.splice(slider.i, 1);
                    slider.max = slider.pics.length;
                    if(slider.i === slider.max) slider.i--;
                    slider.currentPic = slider.pics[slider.i];
                    if(!slider.currentPic){
                        // --TODO сделать изображения по умолчанию для канваса
                        // img.src = '/img/empty.jpg';
                        return;
                    }
                    slider.setTitle();
                    slider.currentPic.render();
                    slider.preload(5);
                } else {
                    console.error(answ.message);
                }
            }.bind(this))
        },
        like : function(){
            if(this.currentPic.fav){
                app.logger.info('Already estimated');
                return;
            }
            //todo hardcoded estimate status
            sendRequest('/gallery/estimate',{status : 2, post : this.currentPic.id}, function(answ){
                if(answ.success){
                    this.currentPic.fav = true;
                    app.logger.info('estimated');
                } else {
                    alert('unable to estimate');
                }
            }.bind(this))
            // }
        },
        next : function(){
            if(this.max === 0) return;
            if(this.i + 1 === this.max){
                app.logger.info('last post');
                return;
            }
            app.slider.slideshow.refresh();
            this.currentPic = this.pics[++this.i < this.max ? this.i : --this.i];
            this.currentPic.render();
            if(this.currentPic.shown) alert('this pic already shown');
            this.setTitle();
            this.preload(5);
            if(this.i > this.pics.length - 10){
                this.addLoadPics()
            }
        },
        prev : function(){
            if(this.max === 0) return;
            app.slider.slideshow.refresh();
            this.currentPic = this.pics[--this.i >= 0 ? this.i : ++this.i];
            this.currentPic.render();
            this.setTitle();
            this.preload(5);
        },
        addLoadPics : function(){
            sendRequest('/gallery/get',{category : slider.currentCat, screen : content.clientWidth/content.clientHeight, offset : this.pics.length}, function(answ){
                app.slider.categoryLoading = false;
                console.log(answ);
                if(!!answ.posts){
                    answ.posts.forEach(function(item){
                        slider.pics.push(new Pic(item));
                    });
                    slider.max = slider.pics.length;
                } else {
                    alert('Unable to reloading category.');
                }
            })

        },
        setPics : function(pics, additional = false){

            // console.log(pics);

            if(additional){
                this.pics.splice(this.i + 2);
                pics.forEach(function(item){
                    slider.pics.push(new Pic(item));
                });
            } else {
                this.pics = [];
                pics.forEach(function(item){
                    slider.pics.push(new Pic(item));
                });
                this.i = 0;
                this.pics[0].onload = this.pics[0].render;
                this.pics[0].loadImg();
                this.pics[0].fillInfo();
                // console.log(this.pics[0]);
                // this.pics[0].render();
                this.currentPic = this.pics[this.i];
            }
            this.max = this.pics.length;
            this.preload(5);
        },
        preload : function(depth){
            for (var i = depth; i > -depth; i--){
                var pic = this.pics[this.i + i];
                if(pic && !pic.cashed){
                    pic.loadImg();
                }
                // console.log(this.pics[this.i + i]);
            }
        },
        setCategory : function(cat, additional = false){
            if(this.categoryLoading) return;
            this.categoryLoading = true;
            if(typeof cat === 'string'){
                categoryName = cat;
            } else {
                categoryName = cat.dataset.value;
            }
            offset = additional ? this.pics.length : 0;
            sendRequest('/gallery/get',{category : categoryName, screen : (content.clientWidth/content.clientHeight).toFixed(1), offset : offset}, function(answ){
                app.slider.categoryLoading = false;
                if(!!answ){
                    if(answ.posts.length === 0){

                        alert('Empty category :(');
                        return;
                    }
                    slider.setPics(answ.posts, additional);

                    if(typeof cat !== 'string'){
                        slider.currentCat = categoryName;
                        slider.categoryCount = cat.querySelector('.category_count').innerText;
                        slider.setTitle(cat.querySelector('.category-name').innerText);
                    } else {
                        slider.categoryCount = answ.info.count;
                        slider.setTitle(cat);
                    }
                } else {
                    alert('Unable to get category.');
                }
            })
        },
        setTitle : function(name = null){
            if(name){
                title.innerHTML = name;
                title_counter.innerHTML = (this.i+1)+'/'+this.categoryCount;
            } else {
                title_counter.innerHTML = (this.i+1)+'/'+this.categoryCount;
            }

        },
        slideshow : {
            progressInterval : null,
            interval : null,
            step : 6000,
            refresh : function(){
                if(!this.interval) return;
                clearInterval(this.interval);
                this.setInterval();
            },
            toggle : function(){
                if(this.interval){
                    this.stop();
                } else {
                    this.start();
                }
            },
            start : function(){
                app.UI.DOM.slideshowButton.setAttribute('src', '/img/pause-icon.png');
                app.UI.DOM.progressBar.style.width ='0';
                app.UI.DOM.progressBar.classList.remove('hidden');
                app.logger.info('slideshow starts');
                this.setInterval();
            },
            stop : function(){
                if(!this.interval) return;
                app.UI.DOM.slideshowButton.setAttribute('src', '/img/play-icon.png');
                app.UI.DOM.progressBar.classList.add('hidden');
                app.UI.DOM.progressBar.style.width ='0';
                app.logger.info('slideshow stops');
                clearInterval(this.interval);
                clearInterval(this.progressInterval);
                this.interval = null;
            },

            setInterval : function(){
                this.progress();
                this.interval = setInterval(function(){
                    this.progress();
                    slider.next();
                }.bind(this), this.step);
            },
            progress : function(){
                clearInterval(this.progressInterval);
                let i = 0;
                this.progressInterval = setInterval(function(){
                    i = i > 100 ? 100 : i;
                    app.UI.DOM.progressBar.style.width = ++i + '%';
                }, this.step / 100)
            }
        }
    },
    logger : {
        displayTimeout : null,
        info : function(message){
            if((typeof message) !== "string"){
                console.log(typeof message);
                console.log(message);
                return;
            }
            // if(this.displayTimeout) clearTimeout(this.displayTimeout);
            let infoContainer = app.UI.DOM.infoBar;
            infoContainer.classList.remove('hidden');

            let msg = document.createElement('div');
            msg.innerHTML = message;
            infoContainer.appendChild(msg);

            this.displayTimeout = setTimeout(function(msg){
                msg.remove();
                if(app.UI.DOM.infoBar.childNodes.length === 0){
                    app.UI.DOM.infoBar.classList.add('hidden');
                }
            }, 3000, msg);

        }
    },
    data : {
        categories : {}
    }
}

const AJAX_PATH = '/ajax';

class Pic {
    constructor(params){
        this.id = params.id;
        this.name = params.file_name;
        this.q = params.q;
        this.tags_artist = params.tags.artist ?? [''];
        this.tags_char = params.tags.character ?? [''];
        this.tags_fan = params.tags.copyright ?? [''];
        this.tags_meta = params.tags.metadata ?? [''];
        this.tags_general = params.tags.general ?? [''];
        this.shown = params.shown;
        this.src = '/gallery/post/' + params.post_id;
        this.fav = params.status === 2;
        this.w = params.width;
        this.h = params.height;
        this.cashed = false;
        this.img = null;
    }
    onload(){

    }
    loadImg(){
        this.img = new Image();
        this.img.src = this.src;
        this.img.onload = this.onload;
        this.img.setAttribute('id', 'img');
        this.cashed = true;
        return this;
    }
    printTags(){
        console.log('ARTIST:');
        console.log(this.tags_artist)

        console.log('COPYRIGHT:');
        console.log(this.tags_fan)

        console.log('CHARACTER:');
        console.log(this.tags_char)

        console.log('META:');
        console.log(this.tags_meta)

        console.log('GENERAL:');
        console.log(this.tags_general)
    }
    render(){
        // console.log(this);
        app.UI.canvas.clear();
        app.UI.canvas.render(this || this.loadImg());
        this.fillInfo();


        // app.UI.DOM.pic_info.innerHTML = '<p>'+this.artists+'</p><p>'+this.q+'</p>';
        if(!slider.viewed.includes(this.id)){
            slider.viewed.push(this.id);
            // console.log(slider.viewed);
            if(slider.viewed.length > 5){
                slider.sendViewed();
            }
        }
    }
    fillInfo(){



        if(this.tags_artist.length === 1 && this.tags_artist[0] === ''){
            app.UI.DOM.pic_artist_list.innerHTML = '—';
        } else {
            app.UI.DOM.pic_artist_list.innerHTML = '';
            this.tags_artist.forEach(function(artist){
                if(artist !== '') app.UI.DOM.pic_artist_list.innerHTML += '<li class="pic-filter">'+artist+'</li>';
            });
        }
        if(this.tags_char.length === 1 && this.tags_char[0] === ''){
            app.UI.DOM.pic_char_list.innerHTML = '—';
        } else {
            app.UI.DOM.pic_char_list.innerHTML = '';
            this.tags_char.forEach(function(char){
                if(char !== '') app.UI.DOM.pic_char_list.innerHTML += '<li class="pic-filter">'+char+'</li>';
            });
        }

        if(this.tags_fan.length === 1 && this.tags_fan[0] === ''){
            app.UI.DOM.pic_fan_list.innerHTML = '—';
        } else {
            app.UI.DOM.pic_fan_list.innerHTML = '';
            this.tags_fan.forEach(function(fan){
                if(fan !== '') app.UI.DOM.pic_fan_list.innerHTML += '<li class="pic-filter">'+fan+'</li>';
            });
        }



    }
}

let slider = app.slider;

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
function sendGet(url, callback){
    $.ajax({
        type : 'GET',
        url : url,
        // data : query,
        // processData : false,
        // dataType : 'JSON',
        success : function(data){
            console.log('success ajax lol');
            // console.log(data);
            callback(data);
        },
        error : function(err){
            console.log('error ajax lol');
            callback(err.responseText);
        }
    });
}
let $infoTimeout = null;
function showInfo(message){
    console.log(message);

}
