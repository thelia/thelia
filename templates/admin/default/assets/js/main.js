(function($, window, document){
    
    $(function($){

        // -- Init datepicker --
        if($('.date').length){
            $('.date').datepicker();
        }

        // -- Init tablesorter --
        if($('.tablesorter').length){
            $('.tablesorter').tablesorter();
        }

        // -- Effect description
        if($('[name=effect]').length){
            var $effectSelect 	= $('[name=effect]'),
                $helpBlock 		= $effectSelect.next('.help-block');

            $effectSelect.change(function(){			
                var description = $(this).find(":selected").data('description');			
                $helpBlock.text(description);
            });
        }

        // -- Confirm Box --
        if($('[data-toggle="confirm"]').length){
            $('[data-toggle="confirm"]').click(function(e){		

                var $link = $(this);
                var modal = $(this).data('target');		

                $(modal).modal('show');

                $(modal).on('shown', function () {
                    $('[data-confirm]').attr('href', $link.attr('href'));
                });

                if($(modal).is(':hidden')){
                    e.preventDefault();
                }

            });
        }        

    });



}(window.jQuery, window, document));

// -- Mini browser --
function miniBrowser(root, url){
    
    $.getJSON(url, {
        root: root
    })
    .done(function(data) {
        var resultat = data;
            
        var breadcrumb = $('<div />');
        $(resultat.breadcrumb).each(function(k, v){
            breadcrumb.append(
                $('<span />').html(' > '),
                $('<a />').attr('href', '#').html(v.display).click(function(e){
                    e.preventDefault();
                    
                    miniBrowser(v.url)
                })
            );
        });
        
        var categories = $('<div />');
        $(resultat.categories).each(function(k, v){
            categories.append(
                $('<p />').append(
                    $('<a />').attr('href', '#').html(v.titre).click(function(e){
                        e.preventDefault();

                        miniBrowser(v.id)
                    })
                )
            );
        });
        
        var products = $('<div />');
        $(resultat.products).each(function(k, v){
            products.append(
                $('<p />').append(
                    $('<a />').attr('href', '#').html(v.titre).click(function(e){
                        e.preventDefault();

                        $('#productToAdd_ref').val(v.ref);
                        $('#productToAdd_titre').val(v.titre);
                        $('#productToAdd_quantite').val(1);
                        
                        manageStock(v.variants, v.promo?v.prix2:v.prix);
                        
                        $('#productToAdd_tva').val(v.tva);
                        
                        $('.productToAddInformation').show();
                        $('#btn_ajout_produit').show();
                    })
                )
            );
        });
        
        $('#fastBrowser_breadcrumb').unbind().empty().append(breadcrumb);
        $('#fastBrowser_categories').unbind().empty().append(categories);
        $('#fastBrowser_products').unbind().empty().append(products);
    })
    .fail(function() {
        console.log('The JSON file cant be read');
    });

}