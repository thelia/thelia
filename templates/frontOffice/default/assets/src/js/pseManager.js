(function ($) {
    "use strict";
    var PseManager = function (element, options) {
        this.$element = $(element);
        this.init(options);
        this.buildForm();
        this.updateProductForm();
    };

    PseManager.DEFAULTS = {
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
        "optionselector": "#pse-options",
        "selectOpt": {},
        "pseId": null,
        "usefallback": false,
        "fallback": $("#pse-options .pse-fallback"),
        "data": null,
        "combination": null,
        "combinationval": null,
        "checkavailability": true,
        "defaultstock": 100
    };

    PseManager.prototype.init = function (options) {
        this.id = objectRtr(options.id);
        this.product = objectRtr(options.product);
        this.name = objectRtr(options.name);
        this.ref = objectRtr(options.ref);
        this.ean = objectRtr(options.ean);
        this.availability = objectRtr(options.availability);
        this.validity = objectRtr(options.validity);
        this.quantity = objectRtr(options.quantity);
        this.promo = objectRtr(options.promo);
        this.new = objectRtr(options.new);
        this.weight = objectRtr(options.weight);
        this.price = objectRtr(options.price);
        this.priceOld = objectRtr(options.priceOld);
        this.submit = objectRtr(options.submit);
        this.optionselector = options.optionselector;
        this.selectOpt = options.selectOpt;
        this.pseId = options.pseId;
        this.usefallback = options.usefallback;
        this.fallback = objectRtr(options.fallback);
        this.data = options.data;
        this.combination = options.combination;
        this.combinationval = options.combinationval;
        this.checkavailability = options.checkavailability;
        this.defaultstock = options.defaultstock;
    };

    PseManager.prototype.buildForm = function () {
        var pse = null,
            combinationId = null,
            combinationValue = null,
            combinationValueId = null,
            combinations = null,
            combinationName = [],
            i;

        var pseManager = this.$element;
        // initialization for the first default pse
        this.pseId = this.getDefaultPSE();

        if (Object.keys(this.data).length > 1) {
            // Use fallback method ?
            this.usefallback = this.useFallback();
            if (this.usefallback) {
                $(this.optionselector + " .option-option").remove();

                for (pse in this.data) {
                    combinations = this.data[pse].combinations;

                    combinationName = [];
                    if (undefined !== combinations) {
                        for (i = 0; i < combinations.length; i++) {
                            combinationName.push(this.combinationval[combinations[i]][0]);
                        }
                    }
                    this.fallback
                        .append("<option value='" + pse + "'>" + combinationName.join(', ') + "</option>");
                }
                $(this.optionselector + " .pse-fallback").on("change", function () {
                    pseManager.psemanager("updateProductForm");
                });

            } else {
                $(this.optionselector + " .option-fallback").remove();

                // get the select for options
                $(this.optionselector + " .pse-option").each(function() {
                    var $option = $(this);
                    if ($option.data("attribute") in this.combination) {
                        pseManager.selectOpt[$option.data("attribute")] = $option; // jshint ignore:line
                        $option.on("change", function () {
                            pseManager.psemanager("updateProductForm");
                        });
                    } else {
                        // not affected to this product -> remove
                        $option.closest(".option").remove();
                    }
                });

                // build select
                for (combinationValueId in this.combinationval) {
                    combinationValue = this.combinationval[combinationValueId];
                    this.selectOpt[combinationValue[1]]
                        .append("<option value='" + combinationValueId + "'>" + combinationValue[0] + "</option>");
                }

                this.setPseForm();
            }
        }
    };

    PseManager.prototype.useFallback = function () {
        var pse = null,
            count = -1,
            pseCount = 0,
            combinations,
            i;

        for (pse in this.data) {
            combinations = this.data[pse].combinations;

            if (undefined !== combinations) {
                pseCount = 0;
                for (i = 0; i < combinations.length; i++) {
                    pseCount += this.combinationval[combinations[i]][1];
                }
                if (count == -1) {
                    count = pseCount;
                } else if (count != pseCount) {
                    return true;
                }
            }
        }

        return (count <= 0);
    };

    PseManager.prototype.setPseForm = function (id) {
        var pse = null,
            combinationValueId;
        pse = this.data[id || this.pseId || this.getDefaultPSE()];

        if (undefined !== pse) {
            if (this.usefallback) {
                this.fallback.val(pse.id);
            } else if (undefined !== pse) {
                for (var i = 0; i < pse.combinations.length; i++) {
                    combinationValueId = pse.combinations[i];
                    this.selectOpt[this.combinationval[combinationValueId][1]].val(pse.combinations[i]) // jshint ignore:line
                }
            }
        }
    };

    PseManager.prototype.updateProductForm = function () {
        var pseId = null,
            selection;

        if (Object.keys(this.data).length > 1) {

            if (this.usefallback) {
                pseId = this.fallback.val();
            } else {
                // get form data
                selection = this.getFormSelection();
                // get the pse
                pseId = this.pseExist(selection);

                if (!pseId) {
                    // not exists, revert
                    this.displayNotice();
                    this.setPseForm();
                } else {
                    this.validity.hide();
                }
            }

            this.id.val(pseId);
            this.pseId = pseId;
        }

        // Update UI
        this.updateProductUI();
    };

    PseManager.prototype.getFormSelection = function () {
        var selection = [],
            combinationId;

        for (combinationId in this.selectOpt) {
            selection.push(this.selectOpt[combinationId].val());
        }

        return selection;
    };

    PseManager.prototype.pseExist = function (selection) {
        var pseId,
            pse = null,
            combinations,
            i,
            j,
            existCombination;

        for (pse in this.data) {
            pseId = pse;
            combinations = this.data[pse].combinations;

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
    };

    PseManager.prototype.displayNotice = function () {
        var $validity = this.validity;
        $validity.stop().show('fast', function () {
            setTimeout(function () {
                $validity.stop().hide('fast');
            }, 3000);
        });
    };

    PseManager.prototype.updateProductUI = function () {
        var pse = this.data[this.pseId],
            name = [],
            pseValueId,
            i
            ;

        if (undefined !== pse) {

            this.ref.html(pse.ref);
            // $pse.ean.html(pse.ean);
            // name
            if (Object.keys(this.data).length > 1) {

                for (i = 0; i < pse.combinations.length; i++) {
                    pseValueId = pse.combinations[i];
                    name.push(
                        //PSE_COMBINATIONS[PSE_COMBINATIONS_VALUE[pseValueId][1]].name +
                        //":" +
                        this.combinationval[pseValueId][0]
                    );
                }

                this.name.html(" - " + name.join(", ") + "");
            }

            // promo
            if (pse.isPromo) {
                this.product.addClass("product--is-promo");
            } else {
                this.product.removeClass("product--is-promo");
            }

            // new
            if (pse.isNew) {
                this.product.addClass("product--is-new");
            } else {
                this.product.removeClass("product--is-new");
            }

            // availability
            if (pse.quantity > 0 || !this.checkavailability) {
                this.setProductAvailable(true);

                if (parseInt(this.quantity.val()) > pse.quantity) {
                    this.quantity.val(pse.quantity);
                }
                if (this.checkavailability) {
                    this.quantity.attr("max", pse.quantity);
                } else {
                    this.quantity.attr("max", this.defaultstock);
                    this.quantity.val("1");
                }

            } else {
                this.setProductAvailable(false);
            }

            // price
            if (pse.isPromo) {
                this.priceOld.html(pse.price);
                this.price.html(pse.promo);
            } else {
                this.priceOld.html("");
                this.price.html(pse.price);
            }
        }
        else {
            this.setProductAvailable(false);
        }
    };

    PseManager.prototype.setProductAvailable = function (available) {
        if (available) {
            this.availability
                .removeClass("out-of-stock")
                .addClass("in-stock")
                .attr("href", "http://schema.org/InStock");

            this.submit.prop("disabled", false);
        }
        else {
            this.availability.removeClass("in-stock")
                .addClass("out-of-stock")
                .attr("href", "http://schema.org/OutOfStock");

            this.submit.prop("disabled", true);
        }
    };


    // HELPERS
    PseManager.prototype.getDefaultPSE = function () {
        for (var i in this.data) {
            var pse = this.data[i];
            if (pse.isDefault) {
                return pse.id;
            }
        }

        return this.data[i];
    };

    function objectRtr(obj) {
        if (obj instanceof jQuery) {
            return obj;
        } else if (typeof obj === 'string') {
            return $(obj);
        } else {
            return null;
        }
    }


    // PseManager PLUGIN DEFINITION
    // ==========================
    /**
     *
     * @param option
     * @returns {*}
     * @constructor
     */
    function PSEManagerPlugin(option) {
        return this.each(function () {
            var $this = $(this);
            var data = $this.data('bs.psemanager');
            var options = $.extend({}, PseManager.DEFAULTS, $this.data(), typeof option == 'object' && option);

            if (!data) $this.data('bs.psemanager', (data = new PseManager(this, options)));
            if (typeof option == 'string') data[option]();
        });
    }

    var old = $.fn.collapse;

    $.fn.psemanager = PSEManagerPlugin;
    $.fn.psemanager.Constructor = PseManager;


    // PseManager NO CONFLICT
    // ====================

    $.fn.psemanager.noConflict = function () {
        $.fn.psemanager = old;
        return this;
    };

})(jQuery);