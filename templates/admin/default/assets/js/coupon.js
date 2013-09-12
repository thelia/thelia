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
    couponManager.addRuleAjax = function(id) {
        console.log('addRuleAjax  '+ id);
        // If create
        if(!id) {
            console.log('pushing');
            couponManager.rulesToSave.push(couponManager.ruleToSave);
        } else { // else update
            console.log('editing ' + id);
            couponManager.rulesToSave[id] = couponManager.ruleToSave;
            // reset edit mode to off
            couponManager.ruleIdToUpdate = false;
        }

        // Save
        couponManager.saveRuleAjax();
    };

    // Set rule inputs to allow editing
    couponManager.updateRuleAjax = function(id) {
        couponManager.ruleToUpdate = couponManager.rulesToSave[id];
        console.log('Set id to edit to ' + id);
        couponManager.ruleIdToUpdate = id;

        // Deleting this rule, we will reset it
        delete couponManager.rulesToSave[id];

        // Set the rule selector
        $("#category-rule option").filter(function() {
            return $(this).val() == couponManager.ruleToUpdate.serviceId;
        }).prop('selected', true);

        // Force rule input refresh
        couponManager.loadRuleInputs(couponManager.ruleToUpdate.serviceId, function() {
            couponManager.fillInRuleInputs();
        });
    };

    // Fill in rule inputs
    couponManager.fillInRuleInputs = function() {
        console.log('fillInRuleInputs with');
        console.log(couponManager.ruleToUpdate);
        var operatorId = null;
        var valueId = null;
        var idName = null;

        if(id) {
            couponManager.ruleToUpdate = couponManager.ruleToSave;
        }

        for (idName in couponManager.ruleToUpdate.operators) {
            // Setting idName operator select
            operatorId = idName + '-operator';
            $('#' + operatorId).val(couponManager.ruleToUpdate.operators[idName]);

            valueId = idName + '-value';
            // Setting idName value input
            $('#' + valueId).val(couponManager.ruleToUpdate.values[idName]);
        }
        couponManager.ruleToSave = couponManager.ruleToUpdate;

        var id = couponManager.ruleIdToUpdate;
        console.log('id to edit = ' + id);
        if(id) {
            console.log('setint rulesToSave[' + id + ']');
            console.log(couponManager.ruleToSave);
            couponManager.rulesToSave[id] = couponManager.ruleToSave;
        }
    };

    // Save rules on click
    couponManager.onClickSaveRule = function() {
        $('#constraint-save-btn').on('click', function () {
            couponManager.addRuleAjax(couponManager.ruleIdToUpdate);
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
            couponManager.updateRuleAjax($this.attr('data-int'));

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
            couponManager.loadRuleInputs($(this).val(), function(ruleToSave) {});
        });
    };
    couponManager.onRuleChange();

    // Fill in ready to be saved rule array
    // var onInputsChange = function()
    // In AJAX response

});

// Rule to save

var couponManager = {};
couponManager.ruleToSave = {};
couponManager.ruleIdToUpdate = false;