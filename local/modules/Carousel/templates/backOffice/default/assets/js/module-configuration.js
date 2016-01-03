$(function() {
    // Set proper image ID in delete from
    $('a.image-delete').click(function(ev) {
        $('#image_delete_id').val($(this).data('id'));
    });
});
