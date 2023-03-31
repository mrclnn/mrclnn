window.addEventListener('load', function () {
    app.run();
});

let app = {
    run: function () {
        console.log('application is running now');
        addCategoryFrame.init();
        tagsConfigFrame.init();
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
    collectionToArray: function(collection) {
        if (collection instanceof HTMLCollection) {
            return [].slice.call(collection);
        } else {
            return false
        }
    },
}
let addCategoryFrame = {
    currentFilter : null,
    dom: {
        addCategoryFrame: null,
        frameTitle: null,
        categoriesFrame: null,

        addButton: null,
        nameInput: null,
        matchedTags: null,

        extendSearch: null,
        excludeSearch: null,
        includeSearch: null,

        extendTags: null,
        excludeTags: null,
        includeTags: null,

        clear: null,
        postsCount: null,

        createMatchedTag: function (tagData) {
            let tag = document.createElement('li');
            tag.classList.add('matchedTag');
            tag.setAttribute('data-id', tagData.id);
            tag.innerHTML = tagData.tag;
            return tag;
        },
        createSelectedTag: function (tagDOM) {
            let tag = document.createElement('li');
            tag.classList.add('selectedTag');
            tag.innerHTML = tagDOM.innerHTML;
            tag.setAttribute('data-id', tagDOM.dataset.id);
            return tag;
        },
        removeSelectedTag: function (selectedTagDOM) {
            if (selectedTagDOM.parentNode.classList.contains('selected-tags')) {
                selectedTagDOM.remove();
                addCategoryFrame.changeSelectedTagsList();
            }
        }
    },
    init: function () {
        this.dom.addCategoryFrame = document.querySelector('#add-category-frame');
        this.dom.frameTitle = this.dom.addCategoryFrame.querySelector('.main-frame-title');

        this.dom.addButton = this.dom.addCategoryFrame.querySelector('#add-category');
        this.dom.nameInput = this.dom.addCategoryFrame.querySelector('#add-category-name');
        this.dom.matchedTags = this.dom.addCategoryFrame.querySelector('#matched-tags');

        this.dom.extendSearch = this.dom.addCategoryFrame.querySelector('#extend-search');
        this.dom.excludeSearch = this.dom.addCategoryFrame.querySelector('#exclude-search');
        this.dom.includeSearch = this.dom.addCategoryFrame.querySelector('#include-search');

        this.dom.extendTags = this.dom.addCategoryFrame.querySelector('#extended-tags');
        this.dom.excludeTags = this.dom.addCategoryFrame.querySelector('#excluded-tags');
        this.dom.includeTags = this.dom.addCategoryFrame.querySelector('#included-tags');

        this.dom.clear = this.dom.addCategoryFrame.querySelector('#clear');
        this.dom.postsCount = this.dom.addCategoryFrame.querySelector('#posts-count');
        this.dom.categoriesFrame = document.querySelector('#categories-container');

        this.dom.categoriesFrame.addEventListener('click', function (e) {
            if (e.target.classList.contains('delete-category')) {
                this.deleteCategory(e.target.parentNode.dataset.id);
            }
            if (e.target.classList.contains('update-category')) {
                this.recountCategory(e.target.parentNode.dataset.id);
            }
            if (e.target.classList.contains('edit-category')) {
                this.editCategory(e.target.parentNode);
            }
        }.bind(this));

        this.dom.clear.addEventListener('click', function () {
            this.clear();
        }.bind(this));

        this.dom.addButton.addEventListener('click', function () {
            if(this.dom.addButton.dataset.target === 'create') this.createCategory();
            if(this.dom.addButton.dataset.target === 'update') this.updateCategory();
        }.bind(this));

        this.dom.matchedTags.addEventListener('click', function (e) {
            this.selectTag(e.target);
        }.bind(this));

        [this.dom.extendSearch, this.dom.excludeSearch, this.dom.includeSearch].forEach(function (searchInput) {
            searchInput.addEventListener('input', function (e) {
                this.searchTag(e.target.value);
            }.bind(this))
        }.bind(this));

        [this.dom.extendSearch, this.dom.excludeSearch, this.dom.includeSearch].forEach(function (searchInput) {
            searchInput.addEventListener('focus', function (e) {
                this.currentFilter = e.target.id;
                this.normalizePositionMatchedTags(e.target);
            }.bind(this))
        }.bind(this));

        [this.dom.extendTags, this.dom.excludeTags, this.dom.includeTags].forEach(function (selectedTags) {
            selectedTags.addEventListener('click', function (e) {
                this.dom.removeSelectedTag(e.target);
            }.bind(this))
        }.bind(this));

        this.setPositionForSelectedTags();

    },
    deleteCategory: function (categoryID) {
        // console.log('trying to delete category id: '+categoryID)
        let query = {
            deleteCategory: true,
            categoryID: categoryID
        }
        app.sendRequest('/ajax', query, function (answer) {
            console.log(answer);
        })
    },
    recountCategory: function (categoryID) {
        let query = {
            recountCategory: true,
            categoryID: categoryID
        }
        app.sendRequest('/ajax', query, function(answer){
            if(answer.success){
                let categoryDOM = this.dom.categoriesFrame.querySelector('.category[data-id="'+answer.id+'"] .category-count');
                categoryDOM.innerHTML = answer.count;
            } else {
                console.error(answer.error);
            }

        }.bind(this))
    },
    updateCategory: function () {
        if (!this.checkCategory()) {
            console.log('category not filled, or posts count is 0. return');
            return;
        }
        if (!this.dom.addButton.dataset.id){
            console.log('category id not filled, unable to update category');
            return;
        }
        let associatedTags = this.getTags();
        if (associatedTags === null) {
            console.log('given invalid associatedTags. Unable to create category');
            return;
        }
        let query = {
            updateCategory: true,
            id: this.dom.addButton.dataset.id,
            name: this.dom.nameInput.value.trim(),
            extendTags: associatedTags.extend.toString(),
            excludeTags: associatedTags.exclude.toString(),
            includeTags: associatedTags.include.toString(),
        }
        app.sendRequest('/ajax', query, function (answer) {
            console.log(answer);
        })
    },
    editCategory: function (category) {
        let name = category.querySelector('.category-name').innerHTML
        this.dom.nameInput.value = name;
        let id = category.dataset.id;
        let extendTags = category.querySelectorAll('.extend-tags li.selectedTag');
        let excludeTags = category.querySelectorAll('.exclude-tags li.selectedTag');
        let includeTags = category.querySelectorAll('.include-tags li.selectedTag');

        this.dom.extendTags.innerHTML = '';
        this.dom.excludeTags.innerHTML = '';
        this.dom.includeTags.innerHTML = '';

        extendTags.forEach(function(tag){
            this.dom.extendTags.appendChild(tag.cloneNode(true))
        }.bind(this))

        excludeTags.forEach(function(tag){
            this.dom.excludeTags.appendChild(tag.cloneNode(true))
        }.bind(this))

        includeTags.forEach(function(tag){
            this.dom.includeTags.appendChild(tag.cloneNode(true))
        }.bind(this))

        this.dom.addButton.dataset.target = 'update';
        this.dom.addButton.dataset.id = id;
        this.dom.addButton.value = 'update category';
        this.dom.clear.value = 'cancel';
        this.dom.frameTitle.innerHTML = 'Edit <i>'+name+'</i>';


    },
    updatePostsCount: function (count) {
        this.dom.postsCount.innerHTML = count;
    },
    clear: function () {
        if(this.dom.addButton.dataset.target === 'create'){
            this.dom.extendTags.innerHTML = '';
            this.dom.excludeTags.innerHTML = '';
            this.dom.includeTags.innerHTML = '';
        }
        if(this.dom.addButton.dataset.target === 'update'){
            this.dom.extendTags.innerHTML = '';
            this.dom.excludeTags.innerHTML = '';
            this.dom.includeTags.innerHTML = '';
            this.dom.nameInput.value = '';

            this.dom.addButton.dataset.target = 'create';
            this.dom.addButton.removeAttribute('data-id');
            this.dom.addButton.value = 'add category';
            this.dom.clear.value = 'clear';
            this.dom.frameTitle.innerHTML = 'Add new category';

        }
    },
    changeSelectedTagsList: function () {
        this.getCategoryCount();
    },
    selectTag: function (selectedTag) {

        let target =    this.currentFilter === 'extend-search' ? this.dom.extendTags :
                        this.currentFilter === 'exclude-search' ? this.dom.excludeTags :
                        this.currentFilter === 'include-search' ? this.dom.includeTags : null;
        if(!target) return;

        target.appendChild(this.dom.createSelectedTag(selectedTag));
        this.changeSelectedTagsList();
    },
    setPositionForSelectedTags: function () {
        // console.log('here');
        let extendInput = this.dom.extendSearch.getBoundingClientRect();
        this.dom.extendTags.style.bottom = document.documentElement.clientHeight - extendInput.top + 'px';
        this.dom.extendTags.style.left = extendInput.left + 'px';

        let excludeInput = this.dom.excludeSearch.getBoundingClientRect();
        this.dom.excludeTags.style.bottom = document.documentElement.clientHeight - excludeInput.top + 'px';
        this.dom.excludeTags.style.left = excludeInput.left + 'px';

        let includeInput = this.dom.includeSearch.getBoundingClientRect();
        this.dom.includeTags.style.bottom = document.documentElement.clientHeight - includeInput.top + 'px';
        this.dom.includeTags.style.left = includeInput.left + 'px';
    },
    normalizePositionMatchedTags: function (relativeInput) {
        console.log(this.dom.matchedTags);
        let searchInputRect = relativeInput.getBoundingClientRect();
        this.dom.matchedTags.style.top = searchInputRect.bottom + 'px';
        this.dom.matchedTags.style.left = searchInputRect.left + 'px';
    },
    searchTag: function (searchWord) {
        this.dom.matchedTags.innerHTML = '';
        if (searchWord.length < 3) {
            return;
        }
        console.log('searching word: ' + searchWord + '...');
        app.sendRequest('/ajax', {searchTag: searchWord}, function (answer) {
            answer.tags.forEach(function (tag) {
                this.dom.matchedTags.appendChild(this.dom.createMatchedTag(tag));
            }.bind(this))
        }.bind(this))
    },
    checkCategory: function () {
        return (this.dom.nameInput.value !== '') &&
            (+this.dom.postsCount.innerHTML > 0);
    },
    getTags: function () {

        let extendTagsIds = [];
        let excludeTagsIds = [];
        let includeTagsIds = [];

        let extendTags = app.collectionToArray(this.dom.extendTags.children);
        if (extendTags === false) {
            console.log('given not HTMLCollection. Returning');
            return null;
        }

        let excludeTags = app.collectionToArray(this.dom.excludeTags.children);
        if (excludeTags === false) {
            console.log('given not HTMLCollection. Returning');
            return null;
        }

        let includeTags = app.collectionToArray(this.dom.includeTags.children);
        if (includeTags === false) {
            console.log('given not HTMLCollection. Returning');
            return null;
        }

        extendTags.forEach(function (tag) {
            extendTagsIds.push(tag.dataset.id);
        })

        excludeTags.forEach(function (tag) {
            excludeTagsIds.push(tag.dataset.id);
        })

        includeTags.forEach(function (tag) {
            includeTagsIds.push(tag.dataset.id);
        })

        return {
            extend: extendTagsIds,
            exclude: excludeTagsIds,
            include: includeTagsIds
        };
    },
    createCategory: function () {
        if (!this.checkCategory()) {
            console.log('category not filled, or posts count is 0. return');
            return;
        }
        let associatedTags = this.getTags();
        if (associatedTags === null) {
            console.log('given invalid associatedTags. Unable to create category');
            return;
        }
        let query = {
            addCategory: true,
            count: +this.dom.postsCount.innerHTML,
            name: this.dom.nameInput.value.trim(),
            extendTags: associatedTags.extend.toString(),
            excludeTags: associatedTags.exclude.toString(),
            includeTags: associatedTags.include.toString(),
        }
        app.sendRequest('/ajax', query, function (answer) {
            console.log(answer);
        })

    },
    getCategoryCount: function () {
        let associatedTags = this.getTags();
        if (associatedTags === null) {
            console.log('given invalid associatedTags. Unable to create category');
            return;
        }
        let query = {
            checkCategoryCount: true,
            extendTags: associatedTags.extend.toString(),
            excludeTags: associatedTags.exclude.toString(),
            includeTags: associatedTags.include.toString(),
        }
        app.sendRequest('/ajax', query, function (answer) {
            this.updatePostsCount(answer.count);
        }.bind(this))
    }
}

let tagsConfigFrame = {
    dom : {
        root : null,

        searchTagInput : null,
        selectedTagsFrame : null,
        selectedAliasFrame : null,
        searchAliasInput : null,

        initDOM : function(){
            this.root = document.querySelector('#tags-config')

            this.searchTagInput = this.root.querySelector('#search-tag');
            this.selectedTagsFrame = this.root.querySelector('#selected-tags');
            this.selectedAliasFrame = this.root.querySelector('#tag-alias');
            this.searchAliasInput = this.root.querySelector('#search-alias');

            this.initEvents();
        },
        initEvents : function(){
            this.searchTagInput.addEventListener('input', function(e){
                tagsConfigFrame.searchTag(e.target.value);
            })
        }
    },
    init : function(){

        this.dom.initDOM();


    },

    searchTag : function(tag){
        console.log(tag);
    }
}