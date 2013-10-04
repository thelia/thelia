(function($, window, document){
    
    $(function(){

        // -- Init datepicker --
        if($('.date').length){
            //$('.date').datepicker();
        }

        // -- Init tablesorter --
        /*if($('.tablesorter').length){
            $('.tablesorter').tablesorter({                
                widgets: ["filter", "stickyHeaders"],
                widthFixed : false,
                widgetOptions : {
                    filter_cssFilter : 'input-medium form-control',
                    filter_formatter : {
                        
                        2 : function($cell, indx){                            
                            return $.tablesorter.filterFormatter.uiDateCompare( $cell, indx, {
                                dateFormat: "dd/mm/yy",
                                changeMonth : true,
                                changeYear : true,
                                compare : '='
                            });
                        },
                        
                        3 : function($cell, indx){
                            return $.tablesorter.filterFormatter.uiRange( $cell, indx, {
                                value: 1,
                                min: 1,
                                max: 50,
                                delayed: true,
                                valueToHeader: false,
                                exactMatch: false
                            });
                        }
                    }
                }
            });
        }*/



        // -- Bootstrap tooltip --
        if($('[rel="tooltip"]').length){            
            $('[rel="tooltip"]').tooltip();
        }

        // -- Bootstrap select --
        if($('[data-toggle="selectpicker"]').length){            
            $('[data-toggle="selectpicker"]').selectpicker();
        }

        // -- Confirm Box --
        if($('[data-toggle="confirm"]').length){
            $('[data-toggle="confirm"]').click(function(e){

                var $this = $(this);
                var $modal = $($this.data('target'));

                $modal.modal('show');

                $modal.on('shown', function () {
                    if($this.data('script')){

                        $('[data-confirm]').click(function(){

                            eval($this.data('script'));

                            $modal.modal('hide');
                            return false;
                        });

                    }
                    else{
                        $('[data-confirm]').attr('href', $this.attr('href'));
                    }
                });

                if($modal.is(':hidden')){
                    e.preventDefault();
                }

            });
        }

        // -- Mini browser --
        miniBrowser = function (root, url){
            
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
                            miniBrowser(v.url);
                        })
                    );
                });
                
                var categories = $('<div />');
                $(resultat.categories).each(function(k, v){
                    categories.append(
                        $('<p />').append(
                            $('<a />').attr('href', '#').html(v.titre).click(function(e){
                                e.preventDefault();
                                miniBrowser(v.id);
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
                
                $('#minibrowser-breadcrumb').unbind().empty().append(breadcrumb);
                $('#minibrowser-categories').unbind().empty().append(categories);
            })
            .fail(function() {
                console.log('An error occurred while reading from JSON file');
            });

        }

    });
    
}(window.jQuery, window, document));