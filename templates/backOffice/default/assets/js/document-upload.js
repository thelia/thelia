$(function($){
    // Manage document upload
    $.documentUploadManager = {};

    Dropzone.autoDiscover = false;



    // Remove document on click
    $.documentUploadManager.initDocumentDropZone = function() {
        $.documentUploadManager.onClickDeleteDocument();
        $.documentUploadManager.onClickModal();
        $.documentUploadManager.onModalHidden();
        $.documentUploadManager.sortDocument();
        $.documentUploadManager.onClickToggleVisibilityDocument();

        var documentDropzone = new Dropzone("#documents-dropzone", {
            dictDefaultMessage : $('.btn-browse').html(),
            uploadMultiple: false
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
            $.documentUploadManager.onClickToggleVisibilityDocument();
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
            $.documentUploadManager.sortDocument();
            $.documentUploadManager.onClickToggleVisibilityDocument();
        });
    };

    // Remove document on click
    $.documentUploadManager.onClickDeleteDocument = function() {
        $('.document-manager .document-delete-btn').on('click', function (e) {
            e.preventDefault();
            $("#submit-delete-document").data("element-id", $(this).attr("id"));
            $('#document_delete_dialog').modal("show");

            return false;
        });
    };

    $.documentUploadManager.onModalHidden = function() {
        $("#document_delete_dialog").on('hidden.bs.modal', function (e) {
            $("#submit-delete-document").data("element-id", "");
        });
    };

    $.documentUploadManager.onClickModal = function() {
        $("#submit-delete-document").on('click', function(e){

            var $id= $(this).data("element-id");
            var $this = $("#"+$id);
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
                        $(".document-manager .message").html(
                            errorMessage
                        );
                    }
                }
            }).done(function(data) {
                $greatParent.remove();
                $(".document-manager .message").html(
                    data
                );

                /* refresh position */
                $( "#js-sort-document").children('li').each(function(position, element) {
                    $(element).find('.js-sorted-position').html(position + 1);
                });
            }).always(function() {
                $('#document_delete_dialog').modal("hide");
                $("#submit-delete-document").data("element-id", "");
            });
        });
    };

    // toggle document on click
    $.documentUploadManager.onClickToggleVisibilityDocument = function() {
        $('.document-manager').on('click', '.document-toggle-btn', function (e) {
            e.preventDefault();
            var $this = $(this);
            //$parent.append('<div class="loading" ></div>');
            var $url = $this.attr("href");
            var errorMessage = $this.attr("data-error-message");
            $.ajax({
                type: "GET",
                url: $url,
                statusCode: {
                    404: function() {
                        $(".document-manager .message").html(
                            errorMessage
                        );
                    }
                }
            }).done(function(data) {
                $(".document-manager .message").html(
                    data
                );

                $this.toggleClass("visibility-visible");
            });
            return false;
        });
    };

    $.documentUploadManager.sortDocument = function() {
        $( "#js-sort-document" ).sortable({
            placeholder: "ui-sortable-placeholder col-sm-6 col-md-3",
            change: function( event, ui ) {
                /* refresh position */
                var pickedElement = ui.item;
                var position = 0;
                $( "#js-sort-document").children('li').each(function(k, element) {
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
                /* update */
                var newPosition = ui.item.find('.js-sorted-position').html();
                var documentId = ui.item.data('sort-id');

                $.ajax({
                    type: "POST",
                    url: documentReorder,
                    data: {
                        document_id: documentId,
                        position: newPosition
                    },
                    statusCode: {
                        404: function() {
                            $(".document-manager .message").html(
                                documentReorderErrorMessage
                            );
                        }
                    }
                }).done(function(data) {
                        $(".document-manager .message").html(
                            data
                        );
                    });
            }
        });
        $( "#js-sort-document" ).disableSelection();
    };
});
