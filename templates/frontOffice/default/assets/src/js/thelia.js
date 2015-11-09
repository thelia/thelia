// Avoid `console` errors in browsers that lack a console.
(function() {
    var method;
    var noop = function () {};
    var methods = [
        'assert', 'clear', 'count', 'debug', 'dir', 'dirxml', 'error',
        'exception', 'group', 'groupCollapsed', 'groupEnd', 'info', 'log',
        'markTimeline', 'profile', 'profileEnd', 'table', 'time', 'timeEnd',
        'timeStamp', 'trace', 'warn'
    ];
    var length = methods.length;
    var console = (window.console = window.console || {});

    while (length--) {
        method = methods[length];

        // Only stub undefined methods.
        if (!console[method]) {
            console[method] = noop;
        }
    }
}());


var pseManager = (function($){

    // cache dom elements
    var manager = {};
    var $pse = {};

    function init(){
        $pse = {
            "id": $("#pse-id"),
            "product": $("#product"),
            "name": $("#pse-name"),
            "ref": $("#pse-ref"),
            "ean": $("#pse-ean"),
            "availability": $("#pse-availability"),
            "validity": $("#pse-validity"),
            "quantity": $("#quantity"),
            "promo": $("#pse-promo"),
            "new": $("#pse-new"),
            "weight": $("#pse-weight"),
            "price": $("#pse-price"),
            "priceOld": $("#pse-price-old"),
            "submit": $("#pse-submit"),
            "options": {},
            "pseId": null,
            "useFallback": false,
            "fallback": $("#pse-options .pse-fallback")
        };
    }

    function buildProductForm() {
        var pse = null,
            combinationId = null,
            combinationValue = null,
            combinationValueId = null,
            combinations = null,
            combinationName = [],
            i;

        // initialization for the first default pse
        $pse.pseId = $pse.id.val();

        if (PSE_COUNT > 1) {
            // Use fallback method ?
            $pse.useFallback = useFallback();

            if ($pse.useFallback) {
                $("#pse-options .option-option").remove();

                for (pse in PSE){
                    combinations = PSE[pse].combinations;

                    combinationName = [];
                    if (undefined !== combinations) {
                        for (i = 0; i < combinations.length; i++){
                            combinationName.push(PSE_COMBINATIONS_VALUE[combinations[i]][0]);
                        }
                    }
                    $pse.fallback
                        .append("<option value='" + pse + "'>" + combinationName.join(', ') + "</option>");
                }

                $("#pse-options .pse-fallback").on("change",function(){
                    updateProductForm();
                });

            } else {
                $("#pse-options .option-fallback").remove();

                // get the select for options
                $("#pse-options .pse-option").each(function(){
                    var $option = $(this);
                    if ( $option.data("attribute") in PSE_COMBINATIONS){
                        $pse['options'][$option.data("attribute")] = $option; // jshint ignore:line
                        $option.on("change", updateProductForm);
                    } else {
                        // not affected to this product -> remove
                        $option.closest(".option").remove();
                    }
                });

                // build select
                for (combinationValueId in PSE_COMBINATIONS_VALUE) {
                    combinationValue = PSE_COMBINATIONS_VALUE[combinationValueId];
                    $pse.options[combinationValue[1]]
                        .append("<option value='" + combinationValueId + "'>" + combinationValue[0] + "</option>");
                }

                setPseForm();
            }
        }
    }

    function setPseForm(id) {
        var pse = null,
            combinationValueId;
        pse = PSE[id || $pse.pseId];

        if (undefined !== pse) {
            if ($pse.useFallback) {
                $pse.fallbak.val(pse.id);
            } else if (undefined !== pse) {
                for (var i = 0; i < pse.combinations.length; i++) {
                    combinationValueId = pse.combinations[i];
                    $pse['options'][PSE_COMBINATIONS_VALUE[combinationValueId][1]].val(pse.combinations[i]) // jshint ignore:line
                }
            }
        }
    }

    function updateProductForm() {
        var pseId = null,
            selection;

        if (PSE_COUNT > 1) {

            if ($pse.useFallback) {
                pseId = $pse.fallback.val();
            } else {
                // get form data
                selection = getFormSelection();
                // get the pse
                pseId = pseExist(selection);

                if ( ! pseId ) {
                    // not exists, revert
                    displayNotice();
                    setPseForm();
                } else {
                    $pse.validity.hide();
                }
            }

            $pse.id.val(pseId);
            $pse.pseId = pseId;
        }

        // Update UI
        updateProductUI();
    }

    function displayNotice() {
        var $validity = $pse.validity;
        $validity.stop().show('fast', function(){
            setTimeout(function(){
                $validity.stop().hide('fast');
            }, 3000);
        });
    }

    function updateProductUI() {
        var pse = PSE[$pse.pseId],
            name = [],
            pseValueId,
            i
            ;

        if (undefined !== pse) {

            $pse.ref.html(pse.ref);
            // $pse.ean.html(pse.ean);
            // name
            if (PSE_COUNT > 1) {

                for (i = 0; i < pse.combinations.length; i++) {
                    pseValueId = pse.combinations[i];
                    name.push(
                        //PSE_COMBINATIONS[PSE_COMBINATIONS_VALUE[pseValueId][1]].name +
                        //":" +
                        PSE_COMBINATIONS_VALUE[pseValueId][0]
                    );
                }

                $pse.name.html(" - " + name.join(", ") + "");
            }

            // promo
            if (pse.isPromo) {
                $pse.product.addClass("product--is-promo");
            } else {
                $pse.product.removeClass("product--is-promo");
            }

            // new
            if (pse.isNew) {
                $pse.product.addClass("product--is-new");
            } else {
                $pse.product.removeClass("product--is-new");
            }

            // availability
            if (pse.quantity > 0 || !PSE_CHECK_AVAILABILITY) {
                setProductAvailable(true);

                if (parseInt($pse.quantity.val()) > pse.quantity) {
                    $pse.quantity.val(pse.quantity);
                }
                if (PSE_CHECK_AVAILABILITY) {
                    $pse.quantity.attr("max", pse.quantity);
                } else {
                    $pse.quantity.attr("max", PSE_DEFAULT_AVAILABLE_STOCK);
                    $pse.quantity.val("1");
                }

            } else {
                setProductAvailable(false);
            }

            // price
            if (pse.isPromo) {
                $pse.priceOld.html(pse.price);
                $pse.price.html(pse.promo);
            } else {
                $pse.priceOld.html("");
                $pse.price.html(pse.price);
            }
        }
        else {
            setProductAvailable(false);
        }
    }

    function setProductAvailable(available) {

        if (available) {
            $pse.availability
                .removeClass("out-of-stock")
                .addClass("in-stock")
                .attr("href", "http://schema.org/InStock");

            $pse.submit.prop("disabled", false);
        }
        else {
            $pse.availability.removeClass("in-stock")
                .addClass("out-of-stock")
                .attr("href", "http://schema.org/OutOfStock");

            $pse.submit.prop("disabled", true);
        }
    }

    function pseExist(selection) {
        var pseId,
            pse = null,
            combinations,
            i,
            j,
            existCombination;

        for (pse in PSE){
            pseId = pse;
            combinations = PSE[pse].combinations;

            if (undefined !== combinations) {
                for (i = 0; i < selection.length; i++) {
                    existCombination = false;
                    for (j = 0; j < combinations.length; j++) {
                        if (selection[i] == combinations[j]) {
                            existCombination = true;
                            break;
                        }
                    }
                    if (existCombination === false) {
                        break;
                    }
                }
                if (existCombination) {
                    return pseId;
                }
            }
        }

        return false;
    }

    function useFallback() {
        var pse = null,
            count = -1,
            pseCount = 0,
            combinations,
            i;

        for (pse in PSE){
            combinations = PSE[pse].combinations;

            if (undefined !== combinations) {
                pseCount = 0;
                for (i = 0; i < combinations.length; i++) {
                    pseCount += PSE_COMBINATIONS_VALUE[combinations[i]][1];
                }
                if (count == -1) {
                    count = pseCount;
                } else if (count != pseCount) {
                    return true;
                }
            }
        }

        return (count <= 0);
    }

    function getFormSelection() {
        var selection = [],
            combinationId;

        for (combinationId in $pse.options){
            selection.push($pse.options[combinationId].val());
        }

        return selection;
    }

    manager.load = function(){
        init();
        buildProductForm();
        updateProductForm();
    };

    return manager;

}(jQuery));


/* JQUERY PREVENT CONFLICT */
(function ($) {

    /*  ------------------------------------------------------------------
     callback Function ------------------------------------------------ */
    var confirmCallback = {
        'address.delete': function ($elm) {
            $.post($elm.attr('href'), function (data) {
                if (data.success) {
                    $elm.closest('tr').remove();
                } else {
                    bootbox.alert(data.message);
                }
            });
        }
    };


    /*  ------------------------------------------------------------------
     onLoad Function ------------------------------------------------- */
    $(document).ready(function () {

        // Loader
        var $loader = $('<div class="loader"></div>');
        $('body').append($loader);

        // Display loader if we do ajax call
        $(document)
            .ajaxStart(function () { $loader.show(); })
            .ajaxStop(function () { $loader.hide(); })
            .ajaxError(function () { $loader.hide(); });

        // Check if the size of the window is appropriate for ajax
        var doAjax = ($(window).width() > 768) ? true : false;

        // Main Navigation Hover
        $('.navbar')
            .on('click.subnav', '[data-toggle=dropdown]', function (event) {
                if ($(this).parent().hasClass('open') && $(this).is(event.target)) { return false; }
            })
            .on('mouseenter.subnav', '.dropdown', function () {
                if ($(this).hasClass('open')) { return; }

                $(this).addClass('open');
            })
            .on('mouseleave.subnav', '.dropdown', function () {
                var $this = $(this);

                if (!$this.hasClass('open')) { return; }

                //This will check if an input child has focus. If no then remove class open
                if ($this.find(":input:focus").length === 0) {
                    $this.removeClass('open');
                } else {
                    $this.find(":input:focus").one('blur', function () {
                        $this.trigger('mouseleave.subnav');
                    });
                }
            });

        // Tooltip
        $('body').tooltip({
            selector: '[data-toggle=tooltip]'
        });

        // Confirm Dialog
        $(document).on('click.confirm', '[data-confirm]', function () {
            var $this       = $(this),
                href        = $this.attr('href'),
                callback    = $this.attr('data-confirm-callback'),
                title       = $this.attr('data-confirm') !== '' ? $this.attr('data-confirm') : 'Are you sure?';

            bootbox.confirm(title, function (confirm) {
                if (confirm) {
                    //Check if callback and if it's a function
                    if (callback && $.isFunction(confirmCallback[callback])) {
                        confirmCallback[callback]($this);
                    } else {
                        if (href) {
                            window.location.href = href;
                        } else {
                            // If forms
                            var $form = $this.closest("form");
                            if ($form.size() > 0) {
                                $form.submit();
                            }
                        }
                    }
                }
            });

            return false;
        });

        // Product Quick view Dialog
        $(document).on('click.product-quickview', '.product-quickview', function () {
            if (doAjax) {
                $.get(this.href,
                    function (data) {
                        // Hide all currently active bootbox dialogs
                        bootbox.hideAll();
                        // Show dialog
                        bootbox.dialog({
                            message : $("#product",data),
                            onEscape: function() {
                                bootbox.hideAll();
                            }
                        });
                        window.pseManager.load();
                    }
                );
                return false;
            }
        });

        // Product AddtoCard - OnSubmit
        if (typeof window.PSE_FORM !== "undefined"){
            window.pseManager.load();
        }

        $(document).on('submit.form-product', '.form-product', function () {
            if (doAjax) {
                var url_action  = $(this).attr("action"),
                    product_id  = $("input[name$='product_id']",this).val(),
                    pse_id  = $("input.pse-id",this).val();

                $.ajax({type: "POST", data: $(this).serialize(), url: url_action,
                    success: function(data){
                        $(".cart-container").html($(data).html());
                        // addCartMessageUrl is initialized in layout.tpl
                        $.ajax({url:addCartMessageUrl, data:{ product_id: product_id, pse_id: pse_id },
                            success: function (data) {
                                // Hide all currently active bootbox dialogs
                                bootbox.hideAll();
                                // Show dialog
                                bootbox.dialog({
                                    message : data,
                                    onEscape: function() {
                                        bootbox.hideAll();
                                    }
                                });
                            }
                        });
                    },
                    error: function (e) {
                        console.log('Error.', e);
                    }
                });
                return false;
            }
        });


        // Toolbar
        var $category_products = $('#category-products');
        var $parent = $category_products.parent();

        $parent.on('click.view-mode', '[data-toggle=view]', function () {
            if (($(this).hasClass('btn-grid') && $parent.hasClass('grid')) || ($(this).hasClass('btn-list') && $parent.hasClass('list'))) { return; }

            // Add loader effect
            $loader.show();
            setTimeout(function () { $parent.toggleClass('grid').toggleClass('list'); $loader.hide(); }, 400);

            return false;
        });

        // Login
        var $form_login = $('#form-login');
        $form_login.on('change.account', ':radio', function () {
            if ($(this).val() === '0') {
                $('#password', $form_login).val('').prop('disabled', true); // Disabled (new customer)
            }
            else {
                $('#password', $form_login).prop('disabled', false); // Enabled
            }
        }).find(':radio:checked').trigger('change.account');

        // Mini Newsletter Subscription
        $('#form-newsletter-mini').on('submit.newsletter', function () {
            $.ajax({
                url: $(this).attr('action'),
                type: $(this).attr('method'),
                data: $(this).serialize(),
                dataType: 'json',
                success: function (json) {
                    bootbox.alert(json.message);
                },
                error: function(jqXHR) {
                    try {
                        bootbox.alert($.parseJSON(jqXHR.responseText).message);
                    } catch (err) { // if not json response
                        bootbox.alert(jqXHR.responseText);
                    }
                }
            });

            return false;
        });


        // Forgot Password
        /*
         var $forgot_password = $('.forgot-password', $form_login);
         if($forgot_password.size() > 0) {
         $forgot_password.popover({
         html : true,
         title: 'Forgot Password',
         content: function() {
         return $('#form-forgotpassword').html();
         }
         }).on('click.btn-forgot', function () {

         $('.btn-forgot').click(function () {
         alert('click form');
         return false;
         });

         $('.btn-close').click(function () {
         $forgot_password.popover('hide');
         });

         return false;
         });
         }
         */

        //.Form Filters
        $('#form-filters').each(function () {
            var $form = $(this);

            $form
                .on('change.filter', ':checkbox', function () {
                    $loader.show();
                    $form.submit();
                })
                .find('.group-btn > .btn').addClass('sr-only');
        });

        // Product details Thumbnails
        $(document).on('click.thumbnails', '#product-thumbnails .thumbnail', function () {
            if ($(this).hasClass('active')) { return false; }

            var $productGallery = $(this).closest("#product-gallery");
            $('.product-image > img', $productGallery).attr('src',$(this).attr('href'));
            $('.thumbnail', $productGallery).removeClass('active');
            $(this).addClass('active');

            return false;
        });

        // Show Carousel control if needed
        $('#product-gallery').each(function () {
            if ($('.item', this).size() > 1) {
                $('#product-thumbnails', this).carousel({interval: false}).find('.carousel-control').show();
            }
        });

        // Payment Method
        $('#payment-method').each(function () {
            var $label = $('label', this);
            $label.on('change', ':radio', function ()  {
                $label.removeClass('active');
                $label.filter('[for="' + $(this).attr('id') + '"]').addClass('active');
            }).filter(':has(:checked)').addClass('active');
        });

        // Apply validation
        $('#form-contact, #form-register, #form-address').validate({
            highlight: function (element) {
                $(element).closest('.form-group').addClass('has-error');
            },
            unhighlight: function (element) {
                $(element).closest('.form-group').removeClass('has-error');
            },
            errorElement: 'span',
            errorClass: 'help-block'
        });

        // Toolbar filter
        $('#content').on('change.toolbarfilter', '#limit-top, #sortby-top', function () {
            window.location = $(this).val();
        });

        // Login form (login page)
        $form_login.each(function(){
            var $emailInput = $('input[type="email"]', $form_login),
                $passEnable  = $('#account1', $form_login);

            $emailInput.on('keypress', function() {
                $passEnable.click();
            });
        });

    });

})(jQuery);


// Manage Countries and States form
(function($) {
    $(document).ready(function(){

        var addressState = (function () {

            // A private function which logs any arguments
            initialize = function( element ) {
                var elm = {};

                elm.state = $(element);
                elm.stateId = elm.state.val();
                elm.country = $(elm.state.data('thelia-country'));
                elm.countryId = elm.country.val();
                elm.block = $(elm.state.data('thelia-toggle'));

                elm.states = elm.state.children().clone();
                elm.state.children().remove();

                var updateState = function updateState() {
                    var countryId = elm.country.val(),
                        stateId = elm.state.val(),
                        hasStates = false;

                    if (stateId !== null && stateId !== '') {
                        elm.stateId = stateId;
                    }

                    elm.state.children().remove();

                    elm.states.each(function(){
                        var $state = $(this);

                        if ($state.data("country") == countryId) {
                            $state.appendTo(elm.state);
                            hasStates = true;
                        }
                    });

                    if (hasStates) {
                        // try to select the last state
                        elm.state.val(elm.stateId);
                        elm.block.removeClass("hidden");
                    } else {
                        elm.block.addClass("hidden");
                    }
                };

                elm.country.on('change', updateState);
                updateState();
            };

            return {
                init: function() {

                    $("[data-thelia-state]").each(function(){
                        initialize(this);
                    });

                }
            };

        })();

        addressState.init();
    });
})(jQuery);
