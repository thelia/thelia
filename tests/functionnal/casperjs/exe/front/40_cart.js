casper.test.comment('== Cart ==');

casper.test.begin('Cart', 4, function suite(test) {

    var productUrl = '';

    casper.start(thelia2_base_url, function() {

        productUrl = this.getElementAttribute('a.product-info', 'href');

        this.echo("product : " + productUrl);

        casper.thenOpen(productUrl, function() {
            this.echo(this.getTitle());
        });

    });

    casper.wait(thelia_default_timeout, function(){
        this.capture(screenshot_dir + 'front/40_product.png');
        this.click("#pse-submit");
    });

    casper.wait(thelia_default_timeout, function() {
        this.captureSelector(screenshot_dir + 'front/40_added-to-cart.png', '.bootbox');
        test.assertSelectorHasText('.bootbox h3', 'The product has been added to your cart');
    });


    casper.then(function(){

        this.thenOpen(thelia2_base_url + "cart", function() {
            this.echo(this.getTitle());
            test.assertExists("#cart .table-cart", "Cart table exists");
            test.assertElementCount("#cart .table-cart tbody tr", 2, "Cart contains 1 product")
            var link = this.getElementInfo('#cart .table-cart tbody tr a.thumbnail');
            //require('utils').dump(link);
            test.assertTruthy( link.attributes.href == productUrl, "This is the right product in cart");
            this.capture(screenshot_dir + 'front/40_cart.png');
        });

    });

    casper.run(function() {
        test.done();
    });

});