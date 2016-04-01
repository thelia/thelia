"use strict";
(function($, window){
    $('#side-menu').metisMenu();

    $(window).bind("load resize", function(){
        var topOffset = 52;
        var width = (this.window.innerWidth > 0) ? this.window.innerWidth : this.screen.width;
        if (width < 1200) {
            $('div.navbar-collapse').addClass('collapse');
            topOffset = 104;
        } else {
            $('div.navbar-collapse').removeClass('collapse');
        }

        var height = (((this.window.innerHeight > 0) ? this.window.innerHeight : this.screen.height) - 1) - topOffset;

        if (height < 1) height = 1;
        if (height > topOffset) {
            $("#page-wrapper").css("min-height", (height - topOffset - 25) + "px");
        }
    });

    $(".modal-force-show").modal("show");

    // Autofocus first form field on modal
    $('.modal').on('shown.bs.modal', function(){
        $('input:visible:first', $(this)).focus();
    });

    // Init event trigger
    var event = 'hover';

    if(/Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent)) {
        event = 'click';
    }

    // Toolbar managment
    $('.btn-toolbar').each(function(){
        var $btn = $(this),
            $content = $btn.next('.toolbar-options');

        $btn.toolbar({
            event: event,
            content: $content,
            style: 'info',
            position: 'right'
        });

        $('a', '.tool-items').on('click', function(){
            // If you want to prevent a link is followed, add .no-follow-link class to your link
            if (!$(this).attr('data-toggle') && !$(this).is('.no-follow-link')) {
                window.location = $(this).attr('href');
            }
        });
    });

    // -- Bootstrap tooltip --
    $('[rel="tooltip"]').tooltip();

    // -- Bootstrap select --
    var $selectpicker = $('[data-toggle="selectpicker"]');
    if($selectpicker.length) {
        $selectpicker.selectpicker();
    }

    // -- Confirm Box --
    $('[data-toggle="confirm"]').click(function(e){
        var $this = $(this);
        var $modal = $($this.data('target'));

        $modal.modal('show');

        $modal.on('shown', function (){
            if($this.data('script')) {
                $('[data-confirm]').click(function(){
                    eval($this.data('script'));

                    $modal.modal('hide');
                    return false;
                });

            } else {
                $('[data-confirm]').attr('href', $this.attr('href'));
            }
        });

        if($modal.is(':hidden')) {
            e.preventDefault();
        }
    });
}(window.jQuery, window));