// Manage Countries and States form
(function($) {
    $(document).ready(function(){

        var addressState = (function () {

            // A private function which logs any arguments
            var initialize = function( element ) {
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
