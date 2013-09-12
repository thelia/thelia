$(function($){

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

    // Remove 1 Rule then Save Rules AJAX
    couponManager.removeRuleAjax = function(id) {
        // Delete rule in temporary array
        delete couponManager.rulesToSave[id];
        couponManager.rulesToSave.clean(undefined);

        // Save
        couponManager.saveRuleAjax();
    };

    // Add 1 Rule / or update the temporary Rules array then Save Rules via AJAX
    couponManager.createOrUpdateRuleAjax = function() {
        var id = couponManager.ruleToUpdateId;
        // If create
        if(!id) {
            couponManager.rulesToSave.push(couponManager.ruleToSave);
        } else { // else update
            couponManager.rulesToSave[id] = couponManager.ruleToSave;
            // reset edit mode to off
            couponManager.ruleToUpdateId = false;
        }

        // Save
        couponManager.saveRuleAjax();
    };

    // Set rule inputs to allow editing
    couponManager.updateRuleSelectAjax = function(id) {
        couponManager.ruleToUpdateId = id;
        couponManager.ruleToSave = couponManager.rulesToSave[id];

        // Set the rule selector
        $("#category-rule option").filter(function() {
            return $(this).val() == couponManager.ruleToSave.serviceId;
        }).prop('selected', true);

        // Force rule input refresh
        couponManager.loadRuleInputs(couponManager.ruleToSave.serviceId, function() {
            couponManager.fillInRuleInputs();
        });
    };

    // Fill in rule inputs
    couponManager.fillInRuleInputs = function() {
        var operatorId = null;
        var valueId = null;
        var idName = null;

        var id = couponManager.ruleToUpdateId;
        if(id) {
            couponManager.ruleToSave = couponManager.rulesToSave[id];
        }

        for (idName in couponManager.ruleToSave.operators) {
            // Setting idName operator select
            operatorId = idName + '-operator';
            $('#' + operatorId).val(couponManager.ruleToSave.operators[idName]);

            // Setting idName value input
            valueId = idName + '-value';
            $('#' + valueId).val(couponManager.ruleToSave.values[idName]);
        }
    };

    // Save rules on click
    couponManager.onClickSaveRule = function() {
        $('#constraint-save-btn').on('click', function () {
            if($('#category-rule').val() == 'thelia.constraint.rule.available_for_everyone') {
                // @todo translate + modal
                var r= confirm("Do you really want to set this coupon available to everyone ?");
                if (r == true) {
                    couponManager.createOrUpdateRuleAjax();
                }
            }

        });
    };
    couponManager.onClickSaveRule();

    // Remove rule on click
    couponManager.onClickDeleteRule = function() {
        $('.constraint-delete-btn').on('click', function (e) {
            e.preventDefault();
            var $this = $(this);
            couponManager.removeRuleAjax($this.attr('data-int'));
        });
    };
    couponManager.onClickDeleteRule();

    // Update rule on click
    couponManager.onClickUpdateRule = function() {
        $('.constraint-update-btn').on('click', function (e) {
            e.preventDefault();
            var $this = $(this);
            couponManager.updateRuleSelectAjax($this.attr('data-int'));

            // Hide row being updated
            $this.parent().parent().remove();
        });
    };
    couponManager.onClickUpdateRule();

    // Reload effect inputs when changing effect
    couponManager.onEffectChange = function() {
        $('#effect').on('change', function () {
            var optionSelected = $("option:selected", this);
            $('#effectToolTip').html(optionSelected.attr("data-description"));
        });
    };
    couponManager.onEffectChange();

    // Reload rule inputs when changing effect
    couponManager.onRuleChange = function() {
        $('#category-rule').on('change', function () {
            couponManager.loadRuleInputs($(this).val(), function() {});
        });
    };
    couponManager.onRuleChange();

    // Fill in ready to be saved rule array
    // var onInputsChange = function()
    // In AJAX response

});

// Rule to save

var couponManager = {};
// Rule to be saved
couponManager.ruleToSave = {};
couponManager.ruleToSave.serviceId = false;
couponManager.ruleToSave.operators = {};
couponManager.ruleToSave.values = {};
// Rules payload to save
couponManager.rulesToSave = [];
// Rule being updated id
couponManager.ruleToUpdateId = false;