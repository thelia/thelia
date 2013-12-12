$(function($){
    // Manage picture upload
    $.imageUploadManager = {};

    Dropzone.autoDiscover = false;

    // Remove image on click
    $.imageUploadManager.initImageDropZone = function() {

        $.imageUploadManager.onClickDeleteImage();
        $.imageUploadManager.sortImage();

        var imageDropzone = new Dropzone("#images-dropzone", {
            dictDefaultMessage : $('.btn-browse').html(),
            uploadMultiple: false,
            maxFilesize: 8,
            acceptedFiles: 'image/png, image/gif, image/jpeg'
        });    

        var totalFiles      = 0,
            completedFiles  = 0;

        imageDropzone.on("addedfile", function(file){
            totalFiles += 1;

            if(totalFiles == 1){
                $('.dz-message').hide();
            }
        });

        imageDropzone.on("complete", function(file){
            completedFiles += 1;

            if (completedFiles === totalFiles){
                $('.dz-message').slideDown();
            }
        });

        imageDropzone.on("success", function(file) {
            imageDropzone.removeFile(file);
            $.imageUploadManager.updateImageListAjax();
            $.imageUploadManager.onClickDeleteImage();
        });
        
              

    };

    // Update picture list via AJAX call
    $.imageUploadManager.updateImageListAjax = function() {
        var $imageListArea = $(".image-manager .existing-image");
        $imageListArea.html('<div class="loading" ></div>');
        $.ajax({
            type: "POST",
            url: imageListUrl,
            statusCode: {
                404: function() {
                    $imageListArea.html(
                        imageListErrorMessage
                    );
                }
            }
        }).done(function(data) {
                $imageListArea.html(
                    data
                );
                $.imageUploadManager.onClickDeleteImage();
                $.imageUploadManager.sortImage();
            });
    };

    // Remove image on click
    $.imageUploadManager.onClickDeleteImage = function() {
        $('.image-manager .image-delete-btn').on('click', function (e) {
            e.preventDefault();
            var $this = $(this);
            var $parent = $this.parent();
            var $greatParent = $parent.parent();

            $greatParent.append('<div class="loading" ></div>');
            $greatParent.find('.btn-group').remove();
            var $url = $this.attr("href");
            var errorMessage = $this.attr("data-error-message");
            $.ajax({
                type: "POST",
                url: $url,
                statusCode: {
                    404: function() {
                        $(".image-manager .message").html(
                            errorMessage
                        );
                    }
                }
            }).done(function(data) {
                $greatParent.remove();
                $(".image-manager .message").html(
                    data
                );

                /* refresh position */
                $( "#js-sort-image").children('li').each(function(position, element) {
                    $(element).find('.js-sorted-position').html(position + 1);
                });
            });
            return false;
        });
    };

    $.imageUploadManager.sortImage = function() {
        $( "#js-sort-image" ).sortable({
            placeholder: "ui-sortable-placeholder col-sm-6 col-md-3",
            change: function( event, ui ) {
                /* refresh position */
                var pickedElement = ui.item;
                var position = 0;
                $( "#js-sort-image").children('li').each(function(k, element) {
                    if($(element).data('sort-id') == pickedElement.data('sort-id')) {
                        return true;
                    }
                    position++;
                    if($(element).is('.ui-sortable-placeholder')) {
                        pickedElement.find('.js-sorted-position').html(position);
                    } else {
                        $(element).find('.js-sorted-position').html(position);
                    }
                });
            },
            stop: function( event, ui ) {
                event.preventDefault();

                /* update */
                var newPosition = ui.item.find('.js-sorted-position').html();
                var imageId = ui.item.data('sort-id');

                $.ajax({
                    type: "POST",
                    url: imageReorder,
                    data: {
                        image_id: imageId,
                        position: newPosition
                    },
                    statusCode: {
                        404: function() {
                            $(".image-manager .message").html(
                                imageReorderErrorMessage
                            );
                        }
                    }
                }).done(function(data) {
                    $(".image-manager .message").html(
                        data
                    );
                });
            }
        });
        $( "#js-sort-image" ).disableSelection();
    };
});
