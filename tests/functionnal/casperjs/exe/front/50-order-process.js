casper.test.comment('== Order process ==');

casper.test.begin('Order process', 4, function suite(test) {

    casper.start(thelia2_base_url + "order/delivery", function goToDelivery() {

        casper.waitForSelector(
            '#delivery-module-list-block .radio',
            function then() {
                casper.test.comment('== Page loaded : ' + this.getCurrentUrl());
                test.assertTitle("Billing and delivery - Cart - " + thelia2_store_name, "title is the one expected for url : " + this.getCurrentUrl());
                if (screenshot_enabled) {
                    this.capture(screenshot_dir + 'front/50_delivery_list.png');
                }

                test.assertEval(function () {
                    return __utils__.findAll("#form-cart-delivery table.table-address tr").length >= 1;
                }, "We expect at least one delivery address");

                this.click('form#form-cart-delivery button[type="submit"]');
            },
            function timeout() {
                test.assertElementCount("table.table-cart-mini tbody tr", 1, "cart contain 1 product");
                if (this.getCurrentUrl() == thelia2_base_url + "order/invoice") {
                    test.info("with a virtual product, the delivery page is skipped");
                    test.skip(1);
                } else {
                    this.die("impossible to load delivery methods");
                }
            }
        );

    });

    casper.waitForSelector(
        '.footer-container',
        function(){
            test.assertTitle("My order - Cart - " + thelia2_store_name, "title is the one expected for url : " + this.getCurrentUrl());

            test.assertElementCount("table.table-cart tbody tr", 1, "cart contain 1 product");
            if (screenshot_enabled) {
                this.capture(screenshot_dir + 'front/50_delivery_order.png');
            }
        },
        function(){
            this.die("The 'title' tag didn't change");
        },
        thelia_default_timeout
    );

    casper.run(function() {
        test.done();
    });
});
