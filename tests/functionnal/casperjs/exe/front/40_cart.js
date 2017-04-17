casper.test.comment('== Cart ==');

casper.test.begin('Cart', 5, function suite(test) {

    var productUrl = '';

    casper.start(thelia2_base_url, function() {

        var productsUrl = this.getElementsAttribute('a.product-info', 'href');
        productUrl = productsUrl[Math.floor(Math.random() * productsUrl.length)];

        this.echo("product : " + productUrl);

        this.thenOpen(productUrl, function() {
            this.echo(this.getTitle());
        });

    });

    casper.waitForSelector(
        "#pse-submit",
        function(){
            test.assertExists("#pse-submit", "Add to cart button exists");
            if (screenshot_enabled) {
                this.capture(screenshot_dir + 'front/40_product.png');
            }
            this.echo("Submit add to cart");

            var isDisabled = this.evaluate(function() {
                return document.querySelector("#pse-submit").hasAttribute('disabled');
            });
            
            if (isDisabled) {
                this.skipTest("This product doesn't have any quantity")
            }
            this.click("#pse-submit");
        },
        function(){
            this.die("Add to cart button not found");
        },
        thelia_default_timeout
    );

    casper.waitForSelector(
        '.bootbox h3.text-center',
        function() {
            //this.echo(this.getHTML());
            test.assertSelectorHasText('.bootbox h3.text-center', 'The product has been added to your cart');
            if (screenshot_enabled) {
                this.captureSelector(screenshot_dir + 'front/40_added-to-cart.png', '.bootbox');
            }
        },
        function(){
            this.die("'The product has been added to your cart' pop-in not found");
        },
        thelia_default_timeout
    );

    casper.thenOpen(thelia2_base_url + "cart", function() {
        this.echo(this.getTitle());
        test.assertExists("#cart .table-cart", "Cart table exists");
        test.assertElementCount("#cart .table-cart tbody tr h3.name a", 1, "Cart contains 1 product")

        var link = this.getElementInfo('#cart .table-cart tbody tr h3.name a');
        test.assertTruthy( link.attributes.href == productUrl, "This is the right product in cart");

        if (screenshot_enabled) {
            this.capture(screenshot_dir + 'front/40_cart.png');
        }
    });

    casper.run(function() {
        test.done();
    });

});