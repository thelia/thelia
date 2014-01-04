$(function($){

    // Manage how coupon and conditions are saved
    $.couponManager = {};

    // Condition being updated category id
    $.couponManager.conditionToUpdateServiceId = -1;
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

    // *****************************************
    // ****************** Delete ***************
    // *****************************************
    // Remove condition on click
    $.couponManager.onClickDeleteCondition = function() {
        $('.condition-delete-btn').on('click', function (e) {
            e.preventDefault();
            var $this = $(this);
            var index = $this.attr('data-conditionIndex');
            $.couponManager.conditionToUpdateServiceId = -1;
            $.couponManager.conditionToUpdateIndex = false;
            $.couponManager.removeConditionAjax(index);
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
        }).done(function(data) {
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
            // Set the condition selector to default
            $("#category-condition option").filter(function() {
                return $(this).val() == '-1';
            }).prop('selected', true);
        }).fail(function() {
            $('#condition-add-operators-values').html(
                $.couponManager.intlPleaseRetry
            );
        }).always(function() {
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
            $.couponManager.conditionToUpdateServiceId = $this.attr('data-serviceId');
            $.couponManager.conditionToUpdateIndex = $this.attr('data-conditionIndex');

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
            if ($.couponManager.conditionToUpdateServiceId == -1) {
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
    // Reload effect inputs when changing effect
    $.couponManager.onEffectChange = function() {
        var mainDiv = $('#coupon-type');
        var optionSelected = mainDiv.find('#type option:selected');
        mainDiv.find('.typeToolTip').html(optionSelected.attr('data-description'));

        mainDiv.find('#type').on('change', function () {
            var optionSelected = $('option:selected', this);
            $.couponManager.displayEfffect(optionSelected);

        });
    };
    $.couponManager.onEffectChange();

    $.couponManager.displayEfffect = function(optionSelected) {
        var mainDiv = $('#coupon-type');
        mainDiv.find('.typeToolTip').html(optionSelected.attr('data-description'));

        var inputsDiv = mainDiv.find('.inputs');
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
        });
    };

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
        $maxUsage = $('#max-usage');
        if ($maxUsage.val() == -1) {
            $isUnlimited.prop('checked', true);
            $maxUsage.hide();
            $('#max-usage-label').hide();
        } else {
            $isUnlimited.prop('checked', false);
            $maxUsage.show();
            $('#max-usage-label').show();
        }

        $isUnlimited.change(function(){
            var $this = $(this);
            if ($this.is(':checked')) {
                $('#max-usage').hide().val('-1');
                $('#max-usage-label').hide();
            } else {
                $('#max-usage').show().val('');
                $('#max-usage-label').show();
            }
        });
    };
    $.couponManager.onUsageUnlimitedChange();
});