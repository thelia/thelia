$(function($){

    // Manage how coupon and conditions are saved
    $.couponManager = {};

    // Condition being updated category id
    $.couponManager.conditionToUpdateServiceId = '';
    // Condition being updated index
    $.couponManager.conditionToUpdateIndex = false;

    // AJAX urls
    $.couponManager.urlAjaxSaveConditions = '';
    $.couponManager.urlAjaxDeleteConditions = '';
    $.couponManager.urlAjaxGetConditionSummaries = '';
    $.couponManager.urlAjaxAdminCouponDrawInputs = '';
    $.couponManager.urlAjaxGetConditionInputFromServiceId = '';
    $.couponManager.urlAjaxGetConditionInputFromConditionInterface = '';

    // I18n messages
    $.couponManager.intlPleaseRetry = '';
    $.couponManager.intlPleaseSelectAnotherCondition = '';
    $.couponManager.intlDoYouReallyWantToSetCouponAvailableForEveryOne = '';
    $.couponManager.intlDoYouReallyWantToDeleteThisCondition = '';

    // *****************************************
    // ****************** Delete ***************
    // *****************************************
    // Remove condition on click
    $.couponManager.onClickDeleteCondition = function() {
        $('.condition-delete-btn').on('click', function (e) {
            e.preventDefault();
            if (confirm($.couponManager.intlDoYouReallyWantToDeleteThisCondition)) {
                var $this = $(this);
                var index = $this.data('condition-index');
                $.couponManager.conditionToUpdateServiceId = '';
                $.couponManager.conditionToUpdateIndex = false;
                $.couponManager.removeConditionAjax(index);
            }
        });
    };
    $.couponManager.onClickDeleteCondition();

    // Remove 1 Condition
    $.couponManager.removeConditionAjax = function(index) {
        var url = $.couponManager.urlAjaxDeleteConditions;
        url = url.replace('8888888', index);

        $('#condition-list').html('<div class="loading" ></div>');
        $.ajax({
            url: url,
            statusCode: {
                404: function() {
                    $('#condition-list').html($.couponManager.intlPleaseSelectAnotherCondition);
                },
                500: function() {
                    $('#condition-list').html($.couponManager.intlPleaseSelectAnotherCondition);
                }
            }
        }).done(function() {
            // Reload condition summaries ajax
            $.couponManager.displayConditionsSummary();
        });
    };

    // *****************************************
    // ****************** Save *****************
    // *****************************************

    // Save conditions on click
    $.couponManager.onClickSaveCondition = function() {
        $('#condition-save-btn').on('click', function () {
            if ($('#category-condition').val() == 'thelia.condition.match_for_everyone') {
                var r = confirm($.couponManager.intlDoYouReallyWantToSetCouponAvailableForEveryOne);
                if (r == true) {
                    $.couponManager.saveConditionAjax();
                }
            } else {
                $.couponManager.saveConditionAjax();
            }
        });
    };
    $.couponManager.onClickSaveCondition();

    // Save Conditions AJAX
    $.couponManager.saveConditionAjax = function() {
        var $form = $("#condition-form");
        var data = $form.serialize();
        var url = $form.attr('action');
        url = url.replace('8888888', $.couponManager.conditionToUpdateIndex);
        $('#condition-add-operators-values').html('<div class="loading" ></div>');

        $.post(
            url,
            data
        ).done(function() {
            $.couponManager.displayConditionsSummary();
            $('#condition-add-operators-values').html('');
            $('#condition-add-type').find('.typeToolTip').html('');
            // Set the condition selector to default
            $("#category-condition option").filter(function() {
                return $(this).val() == '';
            }).prop('selected', true);
        }).fail(function() {
            $('#condition-add-operators-values').html(
                $.couponManager.intlPleaseRetry
            );
        }).always(function() {
            $('#condition-save-btn').hide();
            // Reload condition summaries ajax
            $.couponManager.displayConditionsSummary();
        });
    };

    // *****************************************
    // ****************** Update****************
    // *****************************************

    // Update condition on click
    $.couponManager.onClickUpdateCondition = function() {
        $('.condition-update-btn').on('click', function (e) {
            e.preventDefault();
            var $this = $(this);
            $.couponManager.conditionToUpdateServiceId = $this.data('service-id');
            $.couponManager.conditionToUpdateIndex = $this.data('condition-index');

            $.couponManager.updateConditionSelectFromConditionInterfaceAjax(
                $.couponManager.conditionToUpdateIndex,
                $.couponManager.conditionToUpdateServiceId
            );

            // Hide row being updated
            $this.parent().parent().remove();
        });
    };
    $.couponManager.onClickUpdateCondition();

    // Reload condition inputs when changing Condition type
    $.couponManager.onConditionChange = function() {
        $('#category-condition').on('change', function () {
            var $this = $(this);
            var mainDiv = $('#condition-add-type');
            var optionSelected = $('option:selected', this);
            mainDiv.find('.typeToolTip').html(optionSelected.data('description'));

            // Only if add mode
            if (false != $.couponManager.conditionToUpdateIndex) {
                // Reload condition summaries ajax
                $.couponManager.displayConditionsSummary();
            }
            $.couponManager.conditionToUpdateServiceId = $this.val();
            $.couponManager.conditionToUpdateIndex = false;
            $.couponManager.loadConditionInputsFromServiceId($this.val());
        });
    };
    $.couponManager.onConditionChange();

    // Set condition inputs in order to allow editing
    $.couponManager.updateConditionSelectFromConditionInterfaceAjax = function(conditionIndex, serviceId) {
        // Force condition input refresh
        $.couponManager.loadConditionInputsFromConditionInterface(conditionIndex);

        // Set the condition selector
        $("#category-condition option").filter(function() {
            return $(this).val() == serviceId;
        }).prop('selected', true);
    };

    // Reload condition inputs AJAX
    $.couponManager.doAjaxloadConditionInputs = function(url) {
        $('#condition-add-operators-values').html('<div class="loading" ></div>');
        $.ajax({
            url: url,
            statusCode: {
                404: function() {
                    $('#condition-add-operators-values').html(
                        $.couponManager.intlPleaseSelectAnotherCondition
                    );
                },
                500: function() {
                    $('#condition-add-operators-values').html(
                        $.couponManager.intlPleaseSelectAnotherCondition
                    );
                }
            }
        }).done(function(data) {
            $('#condition-add-operators-values').html(data);
            if ($.couponManager.conditionToUpdateServiceId == '') {
                // Placeholder can't be saved
                $('#condition-save-btn').hide();
            } else {
                $('#condition-save-btn').show();
            }
        });
    };

    // Reload condition inputs from Condition ServiceId
    $.couponManager.loadConditionInputsFromServiceId = function(conditionServiceId) {
        var url = $.couponManager.urlAjaxGetConditionInputFromServiceId;
        url = url.replace('conditionId', conditionServiceId);

        return $.couponManager.doAjaxloadConditionInputs(url);
    };
    // Reload condition inputs from Condition index
    $.couponManager.loadConditionInputsFromConditionInterface = function(conditionIndex) {
        var url = $.couponManager.urlAjaxGetConditionInputFromConditionInterface;
        url = url.replace('8888888', conditionIndex);

        return $.couponManager.doAjaxloadConditionInputs(url);
    };


    // ***********************************************
    // *************** Manager Coupon ****************
    // ***********************************************

    $.couponManager.displayEfffect = function(optionSelected) {
        var typeDiv = $('#coupon-type');
        typeDiv.find('.typeToolTip').html(optionSelected.data('description'));

        var inputsDiv = $('.inputs', $('#coupon-inputs'));
        inputsDiv.html('<div class="loading" ></div>');
        var url = $.couponManager.urlAjaxAdminCouponDrawInputs;
        url = url.replace('couponServiceId', optionSelected.val());
        $.ajax({
            type: "GET",
            url: url,
            data: '',
            statusCode: {
                404: function() {
                    inputsDiv.html($.couponManager.intlPleaseRetry);
                },
                500: function() {
                    inputsDiv.html($.couponManager.intlPleaseRetry);
                }
            }
        }).done(function(data) {
            inputsDiv.html(data);

            // Invoke coupon setup funtion, if any
            try {
                couponInputFormSetup();
            }
            catch (ex) {
                // Ignore the error
            }
        });
    };

    // Reload effect inputs when changing effect
    $.couponManager.onEffectChange = function() {
        var typeDiv = $('#coupon-type');
        var optionSelected = typeDiv.find('#type option:selected');
        typeDiv.find('.typeToolTip').html(optionSelected.data('description'));

        typeDiv.find('#type').on('change', function () {
            var optionSelected = $('option:selected', this);
            $.couponManager.displayEfffect(optionSelected);

        });
    };
    $.couponManager.onEffectChange();

    $.couponManager.displayConditionsSummary = function() {
        var mainDiv = $('#condition-list');
        mainDiv.html('<div class="loading" ></div>');
        var url = $.couponManager.urlAjaxGetConditionSummaries;
        $.ajax({
            type: "GET",
            url: url,
            data: '',
            statusCode: {
                404: function() {
                    mainDiv.html($.couponManager.intlPleaseRetry);
                },
                500: function() {
                    mainDiv.html($.couponManager.intlPleaseRetry);
                }
            }
        }).done(function(data) {
            mainDiv.html(data);
            $.couponManager.onClickUpdateCondition();
            $.couponManager.onClickDeleteCondition();
        });
    };

    // Set max usage to unlimited or not
    $.couponManager.onUsageUnlimitedChange = function() {
        var $isUnlimited = $('#is-unlimited');

        if ($('#max-usage').val() == -1) {
            $isUnlimited.prop('checked', true);
            $('#max-usage-data').hide();
        } else {
            $isUnlimited.prop('checked', false);
            $('#max-usage-data').show();
        }

        $isUnlimited.change(function(){
            if ($(this).is(':checked')) {
                $('#max-usage-data').hide();
                $('#max-usage').val('-1');
            } else {
                $('#max-usage').val('');
                $('#max-usage-data').show();
            }
        });
    };

    // Shipping conditions
    $('#is-removing-postage').change(function(ev) {
        if ($(this).is(':checked')) {
            $('.free-postage-conditions').stop().slideDown();
        }
        else {
            $('.free-postage-conditions').stop().slideUp();
        }
    })

    $.couponManager.onUsageUnlimitedChange();

    $('#is-removing-postage').change();
});