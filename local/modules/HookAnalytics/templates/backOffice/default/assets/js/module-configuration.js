$(document).ready(function() {
    $("#hookanalytics-form").on("submit", function(e, data){
        e.preventDefault();
        var form = $(this);

        $('body').append('<div class="modal-backdrop fade in" id="loading-event"><div class="loading"></div></div>');

        $.ajax({
            url: form.attr('action'),
            type: form.attr('method'),
            data: form.serialize()
        }).done(function(){
            $("#loading-event").remove();
        })
        .success(function(data) {
            if (data.error != 0) {
                $("#loading-event").remove();
                $('#hookanalytics-failed-body').html(data.message);
                $("#hookanalytics-failed").modal("show");
            }
        })
        .fail(function(jqXHR, textStatus, errorThrown){
            $("#loading-event").remove();
            $('#hookanalytics-failed-body').html(jqXHR.responseJSON.message);
            $("#hookanalytics-failed").modal("show");
        });

    });
});