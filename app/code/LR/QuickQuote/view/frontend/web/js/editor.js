define([
    'jquery',
    'ko',
    'underscore',
    'uiComponent',
    'LR_QuickQuote/js/model/item',
    'Magento_Catalog/js/price-utils',
    'mage/translate',
    'Magento_Ui/js/modal/confirm',
    'Magento_Customer/js/model/customer',
    'mage/url',
    'mage/template',
    'Magento_Catalog/product/view/validation',
    'LR_QuickQuote/js/knockout.files',
    'Magezon_Core/js/jquery.magnific-popup.min'
], function ($, ko, _, Component, Item, priceUtils, $t, confirm, customer, urlBuilder, mageTemplate) {
    'use strict';

    return Component.extend({

        defaults: {
            items: [],
            mode: 'quickquote',
            template: 'LR_QuickQuote/editor',
            popupSelector: '#quickquote-popup',
            popupMainClass: 'quickquote-popup',
            enableMultiskus: true,
            enableUpload: true,
            showImage: true,
            showPrice: true,
            showQty: true,
            showUom: true,
            showSubTotal: true,
            showAction: true,
            showGrandTotal: true,
            showSku: true,
            searchPlaceholder: $t('Enter SKU or Product Name'),
            createToListBtnLabel: $t('Create New List'),
            addBtnLabel: $t('Add Product'),
            //submitBtnLabel: $t('Add to Cart'),
            submitBtnLabel: $t('Save Quote'),
            grandTotalLabel: $t('SUBTOTAL:'),
            emptyBtnLabel: $t('EMPTY QUICK QUOTE'),
            showAddBtn: false,
            showEmptyBtn: true,
            confirmMessage: $t('Are you sure you would like to empty this list?'),
            multiSkusLabel: $t('Enter Multiple SKUs'),
            multiSkusDescription: $t('Use commas or paragraph to seperate SKUs.'),
            addToListBtnLabel: $t('ADD TO MY LIST'),
            fileRule: $t('File must be in .csv format and include \"SKU\" and \"QTY\" columns'),
            fileSuccessMessage: $t('File was imported sucessfully'),
            fileLoading: false,
            submitLoading: false,
            loadingproduct: false,
            storageKey: 'quickquoteItems',
            instantSearch: true,
            qty: 1,
            showMyListOption: false,
            myListOptionList: {},
            templates: {
                success: '<div data-role="messages" id="messages">' +
                    '<div class="messages"><div class="message message-success success">' +
                    '<div data-ui-id="messages-message-error"><%- data.message %></div></div>' +
                    '</div></div>',
                error: '<div data-role="messages" id="messages">' +
                    '<div class="messages"><div class="message message-error error">' +
                    '<div data-ui-id="messages-message-error"><%- data.message %></div></div>' +
                    '</div></div>'
            },

            listens: {
                loadingproduct: 'loadingProductSpinner'
            }
        },

        cache: [],

        initialize: function () {
            this._super();

            _.bindAll(this,
                'removeItem',
                'duplicateItem',
                'openPopup',
                'onLoadedFile',
                'onProgressFile',
                'onErrorFile',
                'updatePrice'
            );

            var self = this;

            if (this.instantSearch) {
                self.loadProducts();
            }

            $(document).on('click', '.inc,.dec', function () {
                var className=$(this).attr('class').trim();
                var increment_value=$(this).attr('inc');
                increment_value=parseInt(increment_value);
                if (increment_value == 0 || isNaN(increment_value)) {increment_value=1;}
                var minSaleQty=$(this).attr('min');
                if (minSaleQty == 0 || isNaN(minSaleQty)) {minSaleQty=1;}
                minSaleQty=parseInt(minSaleQty);
                var qty=parseInt($(this).parent().find('.input-text.qty').val());
                var new_qty=qty+increment_value;
                if (className=='dec') { new_qty=qty-increment_value; }
                if (new_qty>=minSaleQty) {
                    $(this).parent().find('.input-text.qty').val(new_qty).trigger('change');
                }
                return false;
            });

            $(document).on('click', '.minus', function () {
                var qty=parseInt($(this).parent().find('.input-text.qty').val());
                var new_qty=qty-1;
                if (new_qty >= 1) {
                    $(this).parent().find('.input-text.qty').val(new_qty).trigger('change');
                }
            });

            $(document).on('click', '.plus', function () {
                var qty=parseInt($(this).parent().find('.input-text.qty').val());
                var new_qty=qty+1;
                $(this).parent().find('.input-text.qty').val(new_qty).trigger('change');
            });

            $(self.popupSelector).on('click', '.action-cancel', function(e) {
                e.preventDefault();
                $.magnificPopup.close();
            });

            $(self.popupSelector).on('click', '.action-select', function(e) {
                e.preventDefault();
                console.log("hihihii");
                if (self.currentItem) {
                    var form = $('#product_addtocart_form');
                    if(form.valid()) {
                        var data      = form.serialize();
                        console.log("data",data);
                        var startDate = $(self.popupSelector).find('#zonrentals-from').val();
                        var endDate   = $(self.popupSelector).find('#zonrentals-to').val();
                        if (startDate && endDate) {
                            data += '&rental=1';
                        }
                        self.currentItem.options(data);
                        self.loadItemInfo(self.currentItem);
                        $.magnificPopup.close();
                        return;
                    }
                }
            });

            $(self.popupSelector).on('click', '.addtolist-action-cancel', function(e) {
                e.preventDefault();
                $.magnificPopup.close();
            });

            $('.action-updateprice').on('click', function(e){
                e.preventDefault();
                console.log("this is herer");
                $(this).closest('td').find('.input-text.qty').toggle();
            });

            $(self.popupSelector).on('click', '.addtolist-action-select', function(e) {
                var errorListSelector = '#addtolist-footer-error';
                e.preventDefault();
                if ($(errorListSelector)) {
                    $(errorListSelector).hide();
                }
                var listId = $('#add-mylist-id').val();
                if (listId != '') {
                    $.ajax({
                        url: urlBuilder.build('quickquote/index/loadmylistproduct'),
                        data: {list_id: listId},
                        type: 'get',
                        dataType: 'json',
                        showLoader: true,
                        success: function (res) {
                            if (res.status) {
                                var skus   = [];
                                _.each(res.products, function(product) {
                                    skus[product['sku']] = {qty: 1};
                                });

                                if (self.instantSearch) {
                                    var products =_.filter(self.products(), function(product) {
                                        return skus[product.sku]
                                    });
                                    _.each(self.items(), function(item) {
                                        var itm = item.getData();
                                        if (itm.product === "" || itm.product === '') {
                                            self.removeItem(item);
                                        }
                                    });
                                    _.each(products, function(product) {
                                        self.addItem({
                                            price: product.price,
                                            qty: skus[product.sku]['qty'],
                                            product: product
                                        });
                                    }, this);
                                    self.addItem();
                                    $.magnificPopup.close();
                                } else {
                                    $.ajax({
                                        url: urlBuilder.build('quickquote/index/loadproducts'),
                                        data: { skus: Object.keys(skus),mode: self.mode },
                                        type: 'post',
                                        dataType: 'json',
                                        showLoader: true,
                                        success: function (res) {
                                            if (res.message) alert(res.message);
                                            _.each(self.items(), function(item) {
                                                console.log(item);
                                            });
                                            _.each(self.items(), function(item) {
                                                var itm = item.getData();
                                                if (itm.product === "" || itm.product === '') {
                                                    self.removeItem(item);
                                                }
                                            });
                                            _.each(res.products, function(product) {
                                                self.addItem({
                                                    price: product.price,
                                                    qty: skus[product.sku]['qty'],
                                                    product: product
                                                });
                                            }, this);
                                            self.addItem();
                                            $.magnificPopup.close();
                                        }
                                    });
                                }
                            }
                        }
                    });
                } else {
                    $(errorListSelector).show();
                }
            });

            self.grandTotal = ko.pureComputed(function() {
                var total = 0;
                $.each(self.items(), function() {
                    total += this.subtotal();
                });
                self.saveItemsToLocalStorage();
                return total;
            });

            _.each(this.getItemsFromLocalStorage(), function(item) {
                if (item && (item.product === "" || item.product === '')) {
                    self.removeItem(item);
                } else {
                    this.addItem(item);
                }
            }, this);

            if (_.size(this.getItemsFromLocalStorage()) === 0) {
                this.addItem();
            }

            if (this.myListOptionList && this.myListOptionList.status === true) {
                this.showMyListOption = true;
            }

            return this;
        },

        initObservable: function () {
            this._super()
                .observe('grandTotal skus qty changePrice fileLoading submitLoading fileMessage loadingproduct')
                .observe({
                    items: [],
                    products: []
                });
            return this;
        },

        submitItems: function (elem, event) {
            this.submitLoading(true);
            var items = [];
            _.each(this.items(), function(item) {
                if (item.isValid() && item.product() != '') {
                    var row = {
                        product: item.product().value,
                        options: item.options(),
                        qty: item.qty(),
                        quote_type: item.quoteType(),
                        name: item.product().name
                    };
                    items.push(row);
                }
            });

            if (items.length) {
                var self = this;
                
                $.ajax({
                    url: self.actionUrl,
                    data: {items: items, quote_data:self.getItemsJsonFromLocalStorage(), mode: self.mode},
                    type: 'post',
                    dataType: 'json',
                    success: function (res) {
                        if (res.message) {
                            alert(res.message);
                        }
                        if (res.status) {
                            self.clearLocalStorage();
                        }
                        if (res.redirectUrl) {
                            window.location.href = res.redirectUrl;
                        }
                        self.submitLoading(false);
                    }
                });
            } else {
                this.submitLoading(false);
            }
        },

        loadChildrenIds: function(product) {
            var self = this;
            self.spinnerloading(true);
            $.ajax({
                url: self.loadChildrensUrl,
                data: { id: product.value, type: product.type },
                type: 'post',
                dataType: 'json',
                success: function (res) {
                    if (res.status) {
                        _.each(res.childrens, function(product) {
                            var item = _.findWhere(self.products(), {id: product.id});
                            if (!item) {
                                self.products().push(product);
                            }
                            self.addItem({
                                price: product.price,
                                qty: 1,
                                product: product
                            });
                        });
                        self.spinnerloading(false);
                    }
                }
            });
        },

        addChildrens: function(product, item) {
            item.spinnerloading(true);
            var self = this;
            if (!product.hasOwnProperty('childrens')) {
                $.ajax({
                    url: self.loadChildrensUrl,
                    data: { id: product.value, type: product.type },
                    type: 'post',
                    dataType: 'json',
                    success: function (res) {
                        if (res.status) {
                            _.each(res.childrens, function(product) {
                                var item = _.findWhere(self.products(), {id: product.id});
                                if (!item) {
                                    self.products().push(product);
                                }
                                self.addItem({
                                    price: product.price,
                                    qty: 1,
                                    product: product
                                });
                            });
                            product.childrens = res.childrens;
                            self.removeItem(item);
                        }
                    }
                });
            } else {
                _.each(product.childrens, function(product) {
                    var item = _.findWhere(self.products(), {id: product.id});
                    if (!item) {
                        self.products().push(product);
                    }
                    self.addItem({
                        price: product.price,
                        qty: 1,
                        product: product
                    });
                });
                self.removeItem(item);
            }
        },

        redirectToMyList: function () {
            window.location.href = urlBuilder.build('b2bmage/customer/account_mylist');
            return;
        },

        addToListToProducts: function () {
            var self = this;
            $.ajax({
                url: urlBuilder.build('quickquote/index/loadmylist'),
                data: {},
                type: 'get',
                dataType: 'json',
                showLoader: true,
                beforeSend: function () {
                    $(self.popupSelector + ' .quickquote-popup-content').html('');
                    $(self.popupSelector).addClass('quickquote-loading');
                },
                success: function (res) {
                    if (res.list) {
                        var addListContent = '<div class="ajaxquote-content">';
                        addListContent = '<div class="ajaxquote-content_left">';
                        addListContent += '<label class="label"><strong>Add to List</strong></label>';
                        addListContent += res.list;
                        addListContent += '<div for="addtolist-footer" class="mage-error" id="addtolist-footer-error">This is a required field.</div>';
                        addListContent += '</div>';
                        addListContent += '</div>';
                        addListContent += '<div class="ajaxquote-footer">';
                        addListContent += '<a href="#" class="addtolist-action-cancel btn btn-secondary">Cancel</a>';
                        addListContent += '<button class="action primary addtolist-action-select btn btn-primary">Select</button>';
                        addListContent += '</div>';
                        $(self.popupSelector + ' .quickquote-popup-content').html(addListContent);
                        $(self.popupSelector + ' .quickquote-popup-content').trigger('contentUpdated');
                        $(self.popupSelector).removeClass('quickquote-loading');

                        $('#addtolist-footer-error').hide();

                        $.magnificPopup.open({
                            items: {
                                src: self.popupSelector
                            },
                            type: 'inline',
                            removalDelay: 300,
                            mainClass: self.popupMainClass,
                            fixedContentPos: true,
                            fixedBgPos: true,
                            overflowY: 'auto',
                            showCloseBtn: false,
                            callbacks: {
                                beforeOpen: function() {
                                    $('body').addClass('quickquote-popup');
                                    this.st.mainClass = $(self.popupSelector).attr('data-effect');
                                },
                                beforeClose: function() {
                                    this.st.mainClass = '';
                                    $(self.popupSelector).css('transition', '');
                                    $('body').removeClass('quickquote-popup');
                                }
                            }
                        }, 0);
                    } else {
                        alert($t('List records not available.'));
                    }
                }
            });
        },

        addItem: function (data) {
            _.each(this.items(), function(item) {
                if (item) {
                    var itm = item.getData();
                    if (itm.product === "" || itm.product === '') {
                        return;
                    }
                }
            });
            if (!data) {
                data = {};
            }
            data = ko.toJS(data);
            data['parent'] = this;
            var item = new Item(data);
            this.items.push(item);
        },

        removeItem: function (item) {
            this.items.remove(item);
            if (_.size(this.getItemsFromLocalStorage()) === 0) {
                this.addItem();
            }
        },

        updatePrice: function (item) {
            console.log("this",$(this));
            var data = item.getData();
            
            console.log("data = ",data);
            console.log("data price = "+data.price);
            /* data['parent'] = this;
            this.items.push(new Item(data)); */
        },

        duplicateItem: function (item) {
            var data = item.getData();
            data['parent'] = this;
            this.items.push(new Item(data));
        },

        loadProducts: function() {
            if (!this.productLoaded) {
                var self = this;
                self.loadingproduct(true);
                $.ajax({
                    url: self.ajaxUrl,
                    data: {mode: self.mode},
                    type: 'post',
                    dataType: 'json',
                    success: function (res) {
                        if (res.message) alert(res.message);
                        self.products(res.products);
                        self.loadingproduct(false);
                    }
                });
                self.productLoaded = true;
            }
        },

        /**
         * FormatPrice
         */
        getFormattedPrice: function (price) {
            return priceUtils.formatPrice(price, this.priceFormat);
        },

        openPopup: function(item) {
            var self = this;

            if (!this.cache[item.product().value]) {
                $.ajax({
                    url: item.product().url + '?options=cart',
                    data: {product: item.product().value, lr_qq: 1,mode: self.mode},
                    type: 'post',
                    dataType: 'json',
                    beforeSend: function () {
                        $(self.popupSelector + ' .quickquote-popup-content').html('');
                        $(self.popupSelector).addClass('quickquote-loading');
                    },
                    success: function (res) {
                        if (res.message) alert(res.message);
                        if (res.html) {
                            $(self.popupSelector + ' .quickquote-popup-content').html(res.html);
                            $(self.popupSelector + ' .quickquote-popup-content').trigger('contentUpdated');
                            $(self.popupSelector + ' #product_addtocart_form').validation();
                            $(self.popupSelector).removeClass('quickquote-loading');
                            setTimeout(function() {
                                self.editOptions(item.options());
                            }, 200);
                            self.cache[item.product().value] = res.html;
                        }
                    }
                });
            } else {
                var html = self.cache[item.product().value];
                $(self.popupSelector).addClass('quickquote-loading');
                $(self.popupSelector + ' .quickquote-popup-content').html(html);
                $(self.popupSelector + ' .quickquote-popup-content').trigger('contentUpdated');
                $(self.popupSelector + ' #product_addtocart_form').validation();
                setTimeout(function() {
                    self.editOptions(item.options());
                    setTimeout(function() {
                        $(self.popupSelector).removeClass('quickquote-loading');
                    }, 300);
                }, 200);
            }

            $.magnificPopup.open({
                items: {
                    src: self.popupSelector
                },
                type: 'inline',
                removalDelay: 300,
                mainClass: self.popupMainClass,
                fixedContentPos: true,
                fixedBgPos: true,
                overflowY: 'auto',
                showCloseBtn: false,
                callbacks: {
                    beforeOpen: function() {
                        $('body').addClass('quickquote-popup');
                        this.st.mainClass = $(self.popupSelector).attr('data-effect');
                    },
                    beforeClose: function() {
                        this.st.mainClass = '';
                        $(self.popupSelector).css('transition', '');
                        $('body').removeClass('quickquote-popup');
                        self.currentItem.checkValid();
                    }
                },
            }, 0);
            this.currentItem = item;
        },

        loadItemInfo: function(item) {
            var self = this;
            console.log("sdfdsf");
            $.ajax({
                url: this.loadItemInfoUrl,
                data: {product: item.product().value, type: item.product().type, options: item.options(),mode: self.mode},
                type: 'post',
                dataType: 'json',
                success: function (res) {
                    if (res.message) alert(res.message);
                    if (res.status) {
                        item.optionsHtml(res.options);
                        item.price(res.price);
                    } else {
                        self.removeItem(item);
                    }
                }
            });
        },

        parseParams: function(str) {
            var re = /([^&=]+)=?([^&]*)/g;
            var decodeRE = /\+/g;
            var decode = function (str) {return decodeURIComponent( str.replace(decodeRE, " ") );};
            var params = {}, e;
            while ( e = re.exec(str) ) {
                var k = decode( e[1] ), v = decode( e[2] );
                if (k.substring(k.length - 2) === '[]') {
                    k = k.substring(0, k.length - 2);
                    (params[k] || (params[k] = [])).push(v);
                }
                else params[k] = v;
            }
            return params;
        },

        editOptions: function(options) {
            var self = this;
            var newOptions = self.parseParams(options);
            $(self.popupSelector).find('input, select, textarea').each(function(index, el) {
                var name = $(this).attr('name');
                if (newOptions[name]) {
                    $(this).val(newOptions[name]);
                    $(this).trigger('change');
                    if ($(this).hasClass('swatch-input')) {
                        var parent = $(this).parents('.swatch-attribute');
                        parent.find('.swatch-option[option-id=' + newOptions[name] + ']').eq(0).trigger('click');
                    }
                }
            });
        },

        getItemsFromLocalStorage: function() {
            return JSON.parse(localStorage.getItem(this.storageKey));
        },

        getItemsJsonFromLocalStorage: function() {
            return localStorage.getItem(this.storageKey);
        },

        saveItemsToLocalStorage: function() {
            var self = this;
            $(".ui-helper-hidden-accessible").remove();
            var items = [];

            _.each(this.items(), function(item) {
                if (item) {
                    var itm = item.getData();
                    if (itm.product === "" || itm.product === '') {
                        self.removeItem(item);
                    }
                }
            });
            this.addItem();

            _.each(this.items(), function(item) {
                items.push(item.getData());
            });

            return localStorage.setItem(this.storageKey, JSON.stringify(items));
        },

        clearLocalStorage: function() {
            return localStorage.removeItem(this.storageKey);
        },

        emptyItems: function() {
            var self = this;
            confirm({
                content: self.confirmMessage,
                actions: {
                    confirm: function () {
                        self.items.removeAll();
                    },
                    always: function (e) {
                        e.stopImmediatePropagation();
                    }
                }
            });
        },

        addToList: function() {
            if (this.skus()) {
                var self = this;
                var result = [];
                var skus   = self.skus().split("\n");
                for (var i = 0; i < skus.length; i++) {
                    var row = skus[i];
                    var skus2 = row.split(",");
                    for (var x = 0; x < skus2.length; x++) {
                        var sku = skus2[x].trim();
                        if ($.inArray( sku, result )==-1) {
                            result.push(sku);
                        }
                    }
                }
                var products =_.filter(self.products(), function(product) {
                    return ($.inArray( product.sku, result )!=-1)
                });

                if (self.instantSearch) {
                    self._processAddToList(products);
                } else {
                    $.ajax({
                        url: self.ajaxUrl,
                        data: { skus: result,mode: self.mode },
                        type: 'post',
                        dataType: 'json',
                        success: function (res) {
                            if (res.message) alert(res.message);
                            self._processAddToList(res.products, skus);
                        }
                    });
                }
            }
        },

        _processAddToList: function(products) {
            var qty = parseInt(this.qty()) ? parseInt(this.qty()) : 1;
            _.each(products, function(product) {
                this.addItem({
                    price: product.price,
                    qty: qty,
                    product: product
                });
            }, this);
            this.skus('');
            this.qty('');
        },

        onLoadedFile: function(file, data) {
            var self   = this;
            var rows   = data.split("\n");
            var result = [];
            var skus   = [];
            for (var i = 1; i < rows.length; i++) {
                var row = rows[i].split(",");
                if (!row.length) continue;
                var qty = row[1] ? row[1] : 1;
                if (row[0] && row[0].trim()) {
                    skus[row[0]] = {
                        qty: row[1]
                    };
                }
            }

            if (self.instantSearch) {
                var products =_.filter(self.products(), function(product) {
                    return skus[product.sku]
                });
                self._processOnLoadedFile(products, skus);
            } else {
                $.ajax({
                    url: self.ajaxUrl,
                    data: { skus: Object.keys(skus),mode: self.mode },
                    type: 'post',
                    dataType: 'json',
                    success: function (res) {
                        if (res.message) alert(res.message);
                        self._processOnLoadedFile(res.products, skus);
                    }
                });
            }
        },

        _processOnLoadedFile: function(products, skus) {
            _.each(products, function(product) {
               console.log("result", skus);
                if(skus)
                {
                    this.addItem({
                        price: product.price,
                        qty: skus[product.sku.toUpperCase()].qty,
                        product: product
                    });
               }
            }, this);
            this.fileLoading(false);
            this.fileMessage('<span class="quickquote-message-success">' + this.fileSuccessMessage + '</span>');
        },

        onErrorFile: function(file, error) {
            this.fileLoading(false);
            this.fileMessage('<span class="quickquote-message-error">' + error + '</span>');
        },

        onProgressFile: function(file) {
            this.fileLoading(true);
            this.fileMessage('');
        },

        loadingProductSpinner: function(status) {
            if (status) {
                $('.page-title').append('<div class="quickquote-spinner"><i></i></div>');
            } else {
                $('.page-title').children('.quickquote-spinner').remove();
            }
        },

        addProductsToMyList: function (data, event) {
            event.preventDefault();
            var element = event.target;
            var addListMsgSelector = '#addtolist-footer-message';
            if ($(addListMsgSelector)) {
                $(addListMsgSelector).hide();
            }

            var listId = element.value;
            if (listId != '') {
                var items = [];
                _.each(this.items(), function(item) {
                    if (item.isValid() && item.product().value) {
                        var row = {
                            product: item.product().value,
                            options: item.options(),
                            qty: item.qty(),
                            quote_type: item.quoteType(),
                            name: item.product().name
                        }
                        items.push(row);
                    }
                });

                if (items.length) {
                    var self = this;
                    $.ajax({
                        url: urlBuilder.build('quickquote/index/addproductstomylist'),
                        data: {items: items, list_id : listId},
                        type: 'post',
                        dataType: 'json',
                        showLoader: true,
                        success: function (res) {
                            if (res.message) {
                                if ($(addListMsgSelector)) {
                                    var template = res.status === false ? self.templates.error : self.templates.success,
                                        message = mageTemplate(template, {
                                            data: res
                                        });
                                    $(addListMsgSelector).html(message).show();
                                } else {
                                    alert(res.message);
                                }
                            }
                        }
                    });
                }
            }
        }
    })
});
