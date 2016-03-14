// Get module hook classnames when a module is selected in the creation dialog
$('#module_id').change(function(ev) {
    var $classnameSelect = $('#classname');

    $classnameSelect.html('<option value="">{intl l='Please wait, loading...'}</option>').prop('disabled', true);

    var moduleId = $("#module_id").val();

    $.ajax({
        url : "{url path="/admin/module-hooks/get-module-hook-classnames"}/" + moduleId,
        success : function(data) {
            $classnameSelect.empty();

            if (data.length == 0) {
                $classnameSelect.append(
                    '<option value="">{intl l='This module cannot be placed in a hook'}</option>'
            )
            } else {
                $(data).each(function(idx, item) {
                    $classnameSelect.empty().append(
                        '<option value="' + item + '">' + item + '</option>'
                    )
                });

                $classnameSelect.prop('disabled', false);

                // For initializing the selected option when editing a module hook
                if ('' != currentClassname) {
                    $classnameSelect.val(currentClassname);
                    currentClassname = '';
                }
            }

            // Get related methods
            $('#classname').change();
        },
        error : function() {
            alert("{intl l='Sorry, an error occurred, please try again.'}");
        }
    })
});


// Get module hook methods when a module is selected in the creation dialog
$('#classname').change(function(ev) {
    var $methodSelect = $('#method');

    var moduleId = $("#module_id").val();
    var classname = $("#classname").val();

    $methodSelect.prop('disabled', true);

    if ('' == classname) {
        $methodSelect.empty();
        return;
    }

    $methodSelect.html('<option value="">{intl l='Please wait, loading...'}</option>');

    $.ajax({
        url : "{url path="/admin/module-hooks/get-module-hook-methods"}/" + moduleId + '/' + classname,
        success : function(data) {
            $methodSelect.empty();

            if (data.length == 0) {
                $methodSelect.append(
                    '<option value="">{intl l='This classname has no method'}</option>'
            )
            } else {
                $(data).each(function(idx, item) {
                    $methodSelect.append(
                        '<option value="' + item + '">' + item + '</option>'
                    )
                });

                $methodSelect.prop('disabled', false);

                // For initializing the selected option when editing a module hook
                if ('' != currentMethod) {
                    $methodSelect.val(currentMethod);
                    currentMethod = '';
                }
            }
        },
        error : function() {
            alert("{intl l='Sorry, an error occurred, please try again.'}");
        }
    })
});
