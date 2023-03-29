window.addEventListener('load', function () {

    app.run();
    addCategoryFrame.init();

});

let app = {
    run: function () {
        console.log('application is running now');
    }
}
let addCategoryFrame = {
    currentFilter : null,
    dom: {
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
        categoriesContainer: null,

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
        this.dom.addButton = document.querySelector('#add-category');
        this.dom.nameInput = document.querySelector('#add-category-name');
        this.dom.matchedTags = document.querySelector('#matched-tags');

        this.dom.extendSearch = document.querySelector('#extend-search');
        this.dom.excludeSearch = document.querySelector('#exclude-search');
        this.dom.includeSearch = document.querySelector('#include-search');

        this.dom.extendTags = document.querySelector('#extended-tags');
        this.dom.excludeTags = document.querySelector('#excluded-tags');
        this.dom.includeTags = document.querySelector('#included-tags');

        this.dom.clear = document.querySelector('#clear');
        this.dom.postsCount = document.querySelector('#posts-count');
        this.dom.categoriesContainer = document.querySelector('#categories-container');

        this.dom.categoriesContainer.addEventListener('click', function (e) {
            if (e.target.classList.contains('delete-category')) {
                this.deleteCategory(e.target.parentNode.dataset.id);
            }
            if (e.target.classList.contains('update-category')) {
                this.updateCategory(e.target.parentNode.dataset.id);
            }
        }.bind(this));

        this.dom.clear.addEventListener('click', function () {
            this.clear();
        }.bind(this));

        this.dom.addButton.addEventListener('click', function () {
            this.createCategory()
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
        sendRequest('/ajax', query, function (answer) {
            console.log(answer);
        })
    },
    updateCategory: function (categoryID) {
        let query = {
            updateCategory: true,
            categoryID: categoryID
        }
        sendRequest('/ajax', query, function(answer){
            if(answer.success){
                let categoryDOM = this.dom.categoriesContainer.querySelector('.category[data-id="'+answer.id+'"] .category-count');
                categoryDOM.innerHTML = answer.count;
            } else {
                console.error(answer.error);
            }

        }.bind(this))
    },
    updatePostsCount: function (count) {
        this.dom.postsCount.innerHTML = count;
    },
    clear: function () {
        this.dom.extendTags.innerHTML = '';
        this.dom.excludeTags.innerHTML = '';
        this.dom.includeTags.innerHTML = '';
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
        sendRequest('/ajax', {searchTag: searchWord}, function (answer) {
            console.log(answer.tags);
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

        let extendTags = collectionToArray(this.dom.extendTags.children);
        if (extendTags === false) {
            console.log('given not HTMLCollection. Returning');
            return null;
        }

        let excludeTags = collectionToArray(this.dom.excludeTags.children);
        if (excludeTags === false) {
            console.log('given not HTMLCollection. Returning');
            return null;
        }

        let includeTags = collectionToArray(this.dom.includeTags.children);
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
        sendRequest('/ajax', query, function (answer) {
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
        sendRequest('/ajax', query, function (answer) {
            this.updatePostsCount(answer.count);
        }.bind(this))
    }
}

function collectionToArray(collection) {
    if (collection instanceof HTMLCollection) {
        return [].slice.call(collection);
    } else {
        return false
    }
}

function sendRequest(url, query, callback) {
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
    });
}