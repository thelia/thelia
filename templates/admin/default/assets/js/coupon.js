$(function($){

    // Manage how coupon and conditions are saved
    $.couponManager = {};
    // Condition to be saved
    $.couponManager.conditionToSave = {};
    $.couponManager.conditionToSave.serviceId = false;
    $.couponManager.conditionToSave.operators = {};
    $.couponManager.conditionToSave.values = {};
    // Conditions payload to save
    $.couponManager.conditionsToSave = [];
    // Condition being updated id
    $.couponManager.conditionToUpdateId = false;

    // Clean array from deleteValue (undefined) keys
    Array.prototype.clean = function(deleteValue) {
        for (var i = 0; i < this.length; i++) {
            if (this[i] == deleteValue) {
                this.splice(i, 1);
                i--;
            }
        }
        return this;
    };

    // Remove 1 Condition then Save Conditions AJAX
    $.couponManager.removeConditionAjax = function(id) {
        // Delete condition in temporary array
        delete $.couponManager.conditionsToSave[id];
        $.couponManager.conditionsToSave.clean(undefined);

        // Save
        $.couponManager.saveConditionAjax();
    };

    // Add 1 Condition / or update the temporary Conditions array then Save Conditions via AJAX
    $.couponManager.createOrUpdateConditionAjax = function() {
        var id = $.couponManager.conditionToUpdateId;
        // If create
        if(!id) {
            $.couponManager.conditionsToSave.push($.couponManager.conditionToSave);
        } else { // else update
            $.couponManager.conditionsToSave[id] = $.couponManager.conditionToSave;
            // reset edit mode to off
            $.couponManager.conditionToUpdateId = false;
        }

        // Save
        $.couponManager.saveConditionAjax();
    };

    // Set condition inputs to allow editing
    $.couponManager.updateConditionSelectAjax = function(id) {
        $.couponManager.conditionToUpdateId = id;
        $.couponManager.conditionToSave = $.couponManager.conditionsToSave[id];

        // Set the condition selector
        $("#category-rule option").filter(function() {
            return $(this).val() == $.couponManager.conditionToSave.serviceId;
        }).prop('selected', true);

        // Force condition input refresh
        $.couponManager.loadConditionInputs($.couponManager.conditionToSave.serviceId, function() {
            $.couponManager.fillInConditionInputs();
        });
    };

    // Fill in condition inputs
    $.couponManager.fillInConditionInputs = function() {
        var operatorId = null;
        var valueId = null;
        var idName = null;

        var id = $.couponManager.conditionToUpdateId;
        if(id) {
            $.couponManager.conditionToSave = $.couponManager.conditionsToSave[id];
        }

        for (idName in $.couponManager.conditionToSave.operators) {
            // Setting idName operator select
            operatorId = idName + '-operator';
            $('#' + operatorId).val($.couponManager.conditionToSave.operators[idName]);

            // Setting idName value input
            valueId = idName + '-value';
            $('#' + valueId).val($.couponManager.conditionToSave.values[idName]);
        }
    };

    // Save conditions on click
    $.couponManager.onClickSaveCondition = function() {
        $('#constraint-save-btn').on('click', function () {
            if($('#category-rule').val() == 'thelia.condition.match_for_everyone') {
//                // @todo translate message + put it in modal
                var r = confirm("Do you really want to set this coupon available to everyone ?");
                if (r == true) {
                    $.couponManager.createOrUpdateConditionAjax();
                }
            } else {
                $.couponManager.createOrUpdateConditionAjax();
            }

        });
    };
    $.couponManager.onClickSaveCondition();

    // Remove condition on click
    $.couponManager.onClickDeleteCondition = function() {
        $('.constraint-delete-btn').on('click', function (e) {
            e.preventDefault();
            var $this = $(this);
            $.couponManager.removeConditionAjax($this.attr('data-int'));
        });
    };
    $.couponManager.onClickDeleteCondition();

    // Update condition on click
    $.couponManager.onClickUpdateCondition = function() {
        $('.condition-update-btn').on('click', function (e) {
            e.preventDefault();
            var $this = $(this);
            $.couponManager.updateConditionSelectAjax($this.attr('data-int'));

            // Hide row being updated
            $this.parent().parent().remove();
        });
    };
    $.couponManager.onClickUpdateCondition();

    // Reload effect inputs when changing effect
    $.couponManager.onEffectChange = function() {
        var optionSelected = $("option:selected", this);
        $('#effectToolTip').html(optionSelected.attr("data-description"));
        $('#effect').on('change', function () {
            var optionSelected = $("option:selected", this);
            $('#effectToolTip').html(optionSelected.attr("data-description"));
        });
    };
    $.couponManager.onEffectChange();

    // Reload condition inputs when changing effect
    $.couponManager.onConditionChange = function() {
        $('#category-rule').on('change', function () {
            $.couponManager.loadConditionInputs($(this).val(), function() {});
        });
    };
    $.couponManager.onConditionChange();

    // Fill in ready to be saved condition array
    // var onInputsChange = function()
    // In AJAX response

    // Set max usage to unlimited or not
    $.couponManager.onUsageUnlimitedChange = function() {
        var isUnlimited = $('#is-unlimited');
        if ($('#max-usage').val() == -1) {
            isUnlimited.prop('checked', true);
            $('#max-usage').hide();
            $('#max-usage-label').hide();
        } else {
            $isUnlimited.prop('checked', false);
            $('#max-usage').show();
            $('#max-usage-label').show();
        }

        isUnlimited.change(function(){
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



