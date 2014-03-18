/* JQUERY PREVENT CONFLICT */
(function ($) {

/*  ------------------------------------------------------------------
    callback Function -------------------------------------------------- */
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
     onLoad Function -------------------------------------------------- */
    $(document).ready(function () {

        // Loader
        var $loader = $('<div class="loader"></div>');
        $('body').append($loader);

        // Display loader if we do ajax call
        $(document)
            .ajaxStart(function () { $loader.show(); })
            .ajaxStop(function () { $loader.hide(); })
            .ajaxError(function () { $loader.hide(); });

        // Main Navigation Hover
        $('.nav-main')
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

        // Toolbar
        var $category_products = $ ('#category-products');
        if ($category_products.size() > 0) {
            var $parent = $category_products.parent();

            $parent.on('click.view-mode', '[data-toggle=view]', function () {
                if (($(this).hasClass('btn-grid') && $parent.hasClass('grid')) || ($(this).hasClass('btn-list') && $parent.hasClass('list'))) { return; }

                // Add loader effect
                $loader.show();
                setTimeout(function () { $parent.toggleClass('grid').toggleClass('list'); $loader.hide(); }, 400);

                return false;
            });
        };

        // Login
        var $form_login = $('#form-login');
        if ($form_login.size() > 0) {
            $form_login.on('change.account', ':radio', function () {
                if ($(this).val() === '0')
                    $('#password', $form_login).val('').prop('disabled', true); // Disabled (new customer)
                else
                    $('#password', $form_login).prop('disabled', false); // Enabled
            }).find(':radio:checked').trigger('change.account');
        }

        // Mini Newsletter Subscription
        var $form_newsletter = $('#form-newsletter-mini');
        if ($form_newsletter.size() > 0) {
            $form_newsletter.on('submit.newsletter', function () {

                $.ajax({
                    url: $(this).attr('action'),
                    type: $(this).attr('method'),
                    data: $(this).serialize(),
                    dataType: 'json',
                    success: function (json) {
                        var $msg = '';
                        if (json.success) {
                            $msg = json.message;
                        } else {
                            $msg = json.message;
                        }
                        bootbox.alert($msg);
                    }
                });

                return false;
            });
        }


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
        $('#product-gallery').each(function () {
            var $item = $('.item', this),
                $thumbnails = $('.thumbnail', this),
                $image = $('.product-image > img', this);

            // Show Carousel control if needed
            if ($item.size() > 1) {
                $('#product-thumbnails', this).carousel({interval: false}).find('.carousel-control').show();
            }

            $(this).on('click.thumbnails', '.thumbnail', function () {
                if ($(this).hasClass('active')) { return false; }

                $image.attr('src',$(this).attr('href'));
                $thumbnails.removeClass('active');
                $(this).addClass('active');

                return false;
            });
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


        if($("body").is(".page-product")){

            var $quantityInput  = $("#quantity");


            var $btnAddToCart   = $(".btn_add_to_cart", $("#form-product-details"));

            var $productMeta    = $("#stock-information");

            var $inStock        = $(".in",$productMeta);
            var $outOfStock     = $(".out",$productMeta);

            var $old_price_container    = $(".old-price", $("#product-details"));

            var $select_quantity        = $(this).find(":selected").attr("data-quantity");


            // Switch Quantity in product page
            $("select", $(".product-options")).change(function(){
                $select_quantity        = $(this).find(":selected").attr("data-quantity");
                var $old_price          = $(this).find(":selected").attr("data-old-price");

                var $best_price         = $(this).find(":selected").attr("data-price");

                $quantityInput.attr("max", $select_quantity);

                // Show Out Of Stock OR In Stock
                if ($select_quantity == 0) {
                    $btnAddToCart.attr("disabled", true);

                    $productMeta.removeClass("in-stock");
                    $productMeta.addClass("out-of-stock");

                    $productMeta.attr("href", "http://schema.org/OutOfStock");

                    $outOfStock.show();
                    $inStock.hide();

                } else {
                    $btnAddToCart.attr("disabled", false);

                    $productMeta.removeClass("out-of-stock");
                    $productMeta.addClass("in-stock");

                    $productMeta.attr("href", "http://schema.org/InStock");

                    $inStock.show();
                    $outOfStock.hide();
                }

                if (parseInt($quantityInput.val()) > parseInt($select_quantity)) {
                    $quantityInput.val($select_quantity);
                }

                if ($old_price_container.size() > 0) {
                    $(".price", $old_price_container).html($old_price);
                    $(".price", $(".special-price")).html($best_price);
                } else {
                    $(".price", $(".regular-price")).html($best_price);
                }

            }).change();

            $quantityInput.focusout(function () {
                $quantityInput.attr("max", $select_quantity);
                if (parseInt($quantityInput.val()) > parseInt($select_quantity)) {
                    $quantityInput.val($select_quantity);
                }
            });
        }

        $(".form-product").submit(function () {
            var url_action      = $(this).attr("action");
            var $cartContainer  = $(".cart-container");
			var product_id  = "product_id=" + $("input[name$='product_id']",this).val();
			
            $.ajax({type: "POST", data: $(this).serialize(), url: url_action,
                    success: function(data){

                        $cartContainer.html($(data).html());
                        $.ajax({url:"ajax/addCartMessage", data:product_id,
                            success: function (data) {
                                bootbox.dialog({
                                    message : data,
                                    buttons : {}
                                });
                            }
                        });
                },
                error: function (e) {
                    console.log('Error.', e);
                }
            });

        return false;
        });

        $('#content').on('change', '#limit-top, #sortby-top', function () {
            window.location = $(this).val()
        });

    });

})(jQuery);

