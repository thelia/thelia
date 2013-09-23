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
            accept: function(file, done) {
                if (file.name == "justinbieber.jpg") {

                    done("Naha, you don't.");
                }
                else { done(); }
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
