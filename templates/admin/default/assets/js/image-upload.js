$(function($){
    // Manage picture upload
    var pictureUploadManager = {};

    Dropzone.autoDiscover = false;

    var imageDropzone = new Dropzone("#images-dropzone", {
        dictDefaultMessage : $('.btn-browse').html(),
        uploadMultiple: false,
        maxFilesize: 8
    });    

    imageDropzone.on("success", function(file) {
        $(".image-manager .dz-file-preview").remove();
        imageDropzone.removeFile(file);
        pictureUploadManager.updateImageListAjax();
    });

    // Update picture list via AJAX call
    pictureUploadManager.updateImageListAjax = function() {
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
            });
    };

    // Remove image on click
    pictureUploadManager.onClickDeleteImage = function() {
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
    pictureUploadManager.onClickDeleteImage();
});
