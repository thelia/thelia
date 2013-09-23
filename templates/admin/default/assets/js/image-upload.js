$(function($){
    // Manage picture upload
    $.imageUploadManager = {};

    Dropzone.autoDiscover = false;

    

    // Remove image on click
    $.imageUploadManager.initImageDropZone = function() {
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
            });
    };

    // Remove image on click
    $.imageUploadManager.onClickDeleteImage = function() {
        $('.image-manager .image-delete-btn').on('click', function (e) {
            e.preventDefault();
            var $this = $(this);
            var $parent = $this.parent();
            $parent.find('a').remove();
            $parent.append('<div class="loading" ></div>');
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
                $parent.parents('tr').remove();

                $(".image-manager .message").html(
                    data
                );
            });
            return false;
        });
    };
    $.imageUploadManager.onClickDeleteImage();
});
