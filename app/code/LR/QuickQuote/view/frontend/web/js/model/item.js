define([
    'jquery',
    'ko', 
    'underscore',
    'mageUtils',
    'mage/translate',
    'LR_QuickQuote/js/jquery-ui'
], function($, ko, _, utils, $t) {

    return function(data) {
        var self             = this;
        this.uid             = utils.uniqueid();
        this.parent          = data.parent;
        this.search          = ko.observable();
        this.product         = ko.observable(data['product'] ? data['product'] : '');
        this.price           = ko.observable(data['price'] ? data['price'] : 0);
        this.qty             = ko.observable(data['qty'] ? parseFloat(data['qty']) : 1);
        this.options         = ko.observable(data['options'] ? data['options'] : []);
        this.optionsHtml     = ko.observable(data['optionsHtml'] ? data['optionsHtml'] : '');
        this.message         = ko.observable(data['message'] ? data['message'] : '');
        this.forceCheckValid = ko.observable(data.hasOwnProperty('forceCheckValid') ? data['forceCheckValid'] : true);
        this.qtyVisible      = ko.observable(data.hasOwnProperty('qtyVisible') ? data['qtyVisible'] : true);
        this.priceVisible      = ko.observable(data.hasOwnProperty('priceVisible') ? data['priceVisible'] : true);
        this.products        = ko.observableArray();
        this.spinnerloading  = ko.observable(false);
        this.loadingPrice    = ko.observable(false);
        var quoteType = data['quoteType'];
        if (!quoteType && data['product'] && data['product']['quoteTypes'] && data['product']['quoteTypes'][0]) {
            quoteType = data['product']['quoteTypes'][0]['value'];
        }
        this.quoteType = ko.observable(quoteType);

        self.getData = function() {
            return {
                product: self.product(),
                price: self.price(),
                qty: self.qty(),
                options: self.options(),
                optionsHtml: self.optionsHtml(),
                message: self.message(),
                forceCheckValid: self.forceCheckValid(),
                quoteType: self.quoteType()
            }
        }

        self.subtotal = ko.pureComputed(function() {
            self.checkValid();
            return (self.product() ? self.price() * self.qty() : 0);
        });

        self.parent.products.subscribe(function(products) {
            if (self.parent.instantSearch) {
                self.products(products);
            }
        });

        self.products.subscribe(function() {
            self.initAutoComplete();
        });

        self.search.subscribe(function(search) {
            if (!self.parent.instantSearch && search.length >= self.parent.minLength) {
                self.spinnerloading(true);
                $.ajax({
                    url: self.parent.ajaxUrl,
                    data: { search: search,mode: self.parent.mode },
                    type: 'post',
                    dataType: 'json',
                    success: function (res) {
                        if (res.message) alert(res.message);
                        self.products(res.products);
                        self.spinnerloading(false);
                        $('#' + self.uid).autocomplete('search', search);
                    }
                });
            }
        });

        self.product.subscribe(function(product) {
            self.options([]);
            self.optionsHtml('');
            if (product) {
                self.price(product.price);
            } else {
                self.price(0);
            }
        });

        self.forceCheckValid.subscribe(function(status) {
            if (status) {
                self.message('');
            }
        });


        self.qty.subscribe(function(qty) {
            self.loadPrice();
        });

        self.loadPrice = function() {
            if (self.product().value) {
                self.loadingPrice(true);
                $.ajax({
                    url: self.parent.loadItemInfoUrl,
                    data: {product: self.product().value, type: self.product().type, options: self.options(), qty: self.qty(), mode: self.mode},
                    type: 'post',
                    dataType: 'json',
                    success: function (res) {
                        if (res.status) {
                            self.price(parseFloat(res.price));
                        }
                        self.loadingPrice(false);
                        if (res.message) self.message(res.message);
                    }
                });
            }
        }

        self.checkValid = function() {
            if (!this.isValid()) {
                self.message($t('Please specify product option(s).'));
            } else {
                self.message('');
            }
        }

        self.isValid = function() {
            var valid = false;
            if (self.product() && self.product().canConfigure && !self.options().length) {
                valid = false
            } else {
                valid = true;
            }
            if (self.product().type == 'grouped') {
                self.qtyVisible(false);
                self.priceVisible(false);
            }
            return valid;
        }

        self.initAutoComplete = function() {
            $('#' + self.uid).focus();
            self._initAutoComplete();
        }

        self._initAutoComplete = function() {

            if (self.parent.instantSearch) {
                var products = self.parent.products();
            } else {
                var products = self.products();
            }

            var element = $('#' + self.uid);
            element.autocomplete({
                messages: {
                    noResults: '',
                    results: function() {

                    }
                },
                minLength: self.parent.minLength,
                appendTo: '#lr-quickquote-ui-' + this.uid,
                source: products,
                select: function( event, ui ) {
                    event.preventDefault();
                    // if (ui.item.type=='grouped') {
                    //     self.parent.addChildrens(ui.item, self);
                    // } else {
                        self.product(ui.item);
                        if (self.product().canConfigure) {
                            self.forceCheckValid(false);
                        } else {
                            self.forceCheckValid(true);
                        }
                        if (self.parent.instantSearch) {
                            element.val(self.product().name);
                        }
                        if (!self.product().canConfigure && self.product().openmodal) {
                            self.parent.openPopup(self);
                        }
                        if (self.product().canConfigure) {
                            self.parent.openPopup(self);
                        }
                        self.forceCheckValid(true);
                    //}
                },
                focus: function(event, ui) {
                    event.preventDefault();
                },
                source: function( request, response ) {
                    if (self.parent.instantSearch) {
                        var result = $.ui.autocomplete.filter( self.products(), request.term );
                        response( result );
                    } else {
                        var ids = {};
                        var result = $.ui.autocomplete.filter( self.products(), request.term );
                        _.each(result, function(item) {
                            ids[item.value] = true;
                        });
                        var matcher = new RegExp( $.ui.autocomplete.escapeRegex(request.term), "i" );
                        var result2 = $.grep( self.products(), function(value) {
                            if (matcher.test( value.search ) && !ids.hasOwnProperty(value.id)) {
                                result.push(value);
                            }
                            return matcher.test( value.search );
                        });
                        response( result );
                    }
                }
            }).data( "ui-autocomplete" )._renderItem = function(ul, item) {
                var $html = '<div <div class="ui-menu-item-wrapper">';
                $html += '<div class="lr-quickquote-item">';
                if (item.img && self.parent.search.showImage) {
                    $html += '<div class="lr-quickquote-item_left"><img src="' + item.img + '" alt="' + item.name + '"/></div>';
                }
                $html += '<div class="lr-quickquote-item_right">';
                $html += '<div class="lr-quickquote-item_title"><a href="#"><span>' + item.name + '</span></a></div>';
                if (self.parent.search.showSku) {
                    $html += '<div class="lr-quickquote-item_sku"><b>SKU#: </b>' + item.sku + '</div>';
                }
                if (self.parent.search.showPrice) {
                    $html += '<div class="lr-quickquote-item_price">' + item.priceHtml + '</div>';
                }
                $html += '</div>';
                $html += '</div>';
                $html += '</div>';
                return $('<li class="ui-menu-item" data-label=' + item.name + '>').data("ui-autocomplete-item",item).append($html).appendTo( ul );
            };
        }
    }
});