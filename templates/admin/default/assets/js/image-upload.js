// Manage picture upload
$(function($){

    var pictureUploadManager = {};

    // Set selected image as preview
    pictureUploadManager.onChangePreviewPicture = function() {
        $('#images input:file').on('change', function () {
            var $this = $(this);
            if ($this.prop("files") && $this.prop("files")[0]) {
                var reader = new FileReader();

                reader.onload = function (e) {
                    $this.parent()
                        .find('img.preview')
                        .attr('src', e.target.result)
                        .width(150)
                        .height(200);
                }

                reader.readAsDataURL($this.prop("files")[0]);
            }
        });
    };
    pictureUploadManager.onChangePreviewPicture();

    // Remove image on click
    pictureUploadManager.onClickDeleteImage = function() {
        $('.image-manager .image-delete-btn').on('click', function (e) {
            console.log('deletingImage');
            e.preventDefault();
            var $this = $(this);
            $this.parent().append('<div class="loading" ></div>');
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
                $this.parent().remove();
                $(".image-manager .message").html(
                    data
                );
            });
        });
    };
    pictureUploadManager.onClickDeleteImage();

    // Remove image on click
    pictureUploadManager.clonePictureInputs = function() {
        var $inputs = $(".image-manager .picture-input");
        if ($inputs.size == 1) {
            console.log('1');
            $(".image-manager .picture-input").last().show();
        } else {
            console.log('+d1');
            $(".image-manager .picture-input").last().clone().appendTo(".image-manager .pictures-input");
        }
    }
});