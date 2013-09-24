$(function($){
    // Manage document upload
    $.documentUploadManager = {};

    Dropzone.autoDiscover = false;

    

    // Remove image on click
    $.documentUploadManager.initDocumentDropZone = function() {
        var documentDropzone = new Dropzone("#documents-dropzone", {
            dictDefaultMessage : $('.btn-browse').html(),
            uploadMultiple: false,
            maxFilesize: 8
        });    

        var totalFiles      = 0,
            completedFiles  = 0;

        documentDropzone.on("addedfile", function(file){
            totalFiles += 1;

            if(totalFiles == 1){
                $('.dz-message').hide();
            }
        });

        documentDropzone.on("complete", function(file){
            completedFiles += 1;

            if (completedFiles === totalFiles){
                $('.dz-message').slideDown();
            }
        });

        documentDropzone.on("success", function(file) {
            documentDropzone.removeFile(file);
            $.documentUploadManager.updateDocumentListAjax();
            $.documentUploadManager.onClickDeleteDocument();
        });
        
              

    };

    // Update picture list via AJAX call
    $.documentUploadManager.updateDocumentListAjax = function() {
        var $documentListArea = $(".document-manager .existing-document");
        $documentListArea.html('<div class="loading" ></div>');
        $.ajax({
            type: "POST",
            url: documentListUrl,
            statusCode: {
                404: function() {
                    $documentListArea.html(
                        documentListErrorMessage
                    );
                }
            }
        }).done(function(data) {
                $documentListArea.html(
                    data
                );
                $.documentUploadManager.onClickDeleteDocument();
            });
    };

    // Remove image on click
    $.documentUploadManager.onClickDeleteDocument = function() {
        $('.document-manager .document-delete-btn').on('click', function (e) {
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
                        $(".document-manager .message").html(
                            errorMessage
                        );
                    }
                }
            }).done(function(data) {
                $parent.parents('tr').remove();

                $(".document-manager .message").html(
                    data
                );
            });
            return false;
        });
    };
    $.documentUploadManager.onClickDeleteDocument();
});
