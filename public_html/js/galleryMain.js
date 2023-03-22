window.addEventListener('load', function(){

    app.run();
    addCategoryFrame.init();

});

let app = {
    run : function(){
        console.log('application is running now');
    }
}
let addCategoryFrame = {
    dom : {
        addButton : null,
        nameInput : null,
        tagSearchInput : null,
        matchedTags : null,
        selectedTags : null,
        clear : null,
        postsCount : null,
        categoriesContainer : null,

        createMatchedTag : function(tagData){
            let tag = document.createElement('li');
            tag.classList.add('matchedTag');
            tag.setAttribute('data-id', tagData.id);
            tag.innerHTML = tagData.tag;
            return tag;
        },
        createSelectedTag : function(tagDOM){
            let tag = document.createElement('li');
            tag.classList.add('selectedTag');
            tag.innerHTML = tagDOM.innerHTML;
            tag.setAttribute('data-id', tagDOM.dataset.id);
            return tag;
        },
        removeSelectedTag : function(selectedTagDOM){
            if(selectedTagDOM.parentNode.id === 'selected-tags'){
                selectedTagDOM.remove();
                addCategoryFrame.changeSelectedTagsList();
            }
        }
    },
    init : function(){
        this.dom.addButton = document.querySelector('#add-category');
        this.dom.nameInput = document.querySelector('#add-category-name');
        this.dom.tagSearchInput = document.querySelector('#tagSearch');
        this.dom.matchedTags = document.querySelector('#matchedTags');
        this.dom.selectedTags = document.querySelector('#selected-tags');
        this.dom.clear = document.querySelector('#clear');
        this.dom.postsCount = document.querySelector('#posts-count');
        this.dom.categoriesContainer = document.querySelector('#categories-container');

        this.dom.categoriesContainer.addEventListener('click', function(e){
            if(e.target.classList.contains('delete-category')){
                this.deleteCategory(e.target.parentNode.dataset.id);
            }
        }.bind(this))

        this.dom.clear.addEventListener('click', function(){
            this.clear();
        }.bind(this))

        this.dom.addButton.addEventListener('click', function(){
            this.createCategory()
        }.bind(this))

        this.dom.matchedTags.addEventListener('click', function(e){
            this.selectTag(e.target);
        }.bind(this))

        this.dom.tagSearchInput.addEventListener('input', function(e){
            this.searchTag(e.target.value);
        }.bind(this));

        this.dom.selectedTags.addEventListener('click', function(e){
            this.dom.removeSelectedTag(e.target);
        }.bind(this));

        this.normalizePositionMatchedTags();

    },
    deleteCategory : function(categoryID){
        // console.log('trying to delete category id: '+categoryID)
        let query = {
            deleteCategory : true,
            categoryID : categoryID
        }
        sendRequest('/ajax', query, function(answer){
            console.log(answer);
        })
    },
    updatePostsCount : function(count){
        this.dom.postsCount.innerHTML = count;
    },
    clear : function(){
        this.dom.selectedTags.innerHTML = '';
    },
    changeSelectedTagsList : function(){
        this.getCategoryCount();
    },
    selectTag : function(selectedTag){
        this.dom.selectedTags.appendChild(this.dom.createSelectedTag(selectedTag));
        this.changeSelectedTagsList();
    },
    normalizePositionMatchedTags : function(){
        let searchInputRect = this.dom.tagSearchInput.getBoundingClientRect();
        this.dom.matchedTags.style.top = searchInputRect.bottom + 'px';
        this.dom.matchedTags.style.left = searchInputRect.left + 'px';
    },
    searchTag : function(searchWord){
        this.dom.matchedTags.innerHTML = '';
        if(searchWord.length < 3){
            console.log('searched word is to small')
            return;
        }
        console.log('searching word: '+searchWord);
        console.log('sending searchTag request...');
        sendRequest('/ajax', {searchTag: searchWord}, function(answer){
            console.log(answer.tags);
            answer.tags.forEach(function(tag){
                this.dom.matchedTags.appendChild(this.dom.createMatchedTag(tag));
            }.bind(this))
        }.bind(this))
    },
    checkCategory : function(){
        return this.dom.nameInput.value !== '';

    },
    getAssociatedTags : function(){
        let associatedTagsIds = [];
        console.log(this.dom.selectedTags.children);
        //todo нельзя перебирать forEach коллекцию которую возвращает .children, есть обходные пути.
        let selectedTags = collectionToArray(this.dom.selectedTags.children);
        if(selectedTags === false){
            console.log('given not HTMLCollection. Returning');
            return null;
        }
        // return false;
        selectedTags.forEach(function(tag){
            console.log(tag);
            associatedTagsIds.push(tag.dataset.id);
        })
        return associatedTagsIds;
    },
    createCategory : function(){
        if(!this.checkCategory()){
            console.log('category not filled, return');
            return;
        }
        let associatedTags = this.getAssociatedTags();
        if(associatedTags === null) {
            console.log('given invalid associatedTags. Unable to create category');
            return;
        }
        let query = {
            addCategory : true,
            name : this.dom.nameInput.value.trim(),
            associatedTags : associatedTags.toString()
        }
        sendRequest('/ajax', query, function(answer){
            console.log(answer);
        })

    },

    getCategoryCount : function(){
        let associatedTags = this.getAssociatedTags();
        if(associatedTags === null) {
            console.log('given invalid associatedTags. Unable to create category');
            return;
        }
        let query = {
            checkCategoryCount : true,
            associatedTags : associatedTags.toString()
        }
        sendRequest('/ajax', query, function(answer){
            this.updatePostsCount(answer.count);
        }.bind(this))
    }
}

function collectionToArray(collection){
    if(collection instanceof HTMLCollection){
        return [].slice.call(collection);
    } else {
        return false
    }
}
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