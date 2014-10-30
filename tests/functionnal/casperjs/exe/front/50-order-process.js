casper.test.comment('== Order process ==');

casper.test.begin('Order process', 4, function suite(test) {

    //first log in user

    casper.start(thelia2_base_url, function() {
        //login
        casper.evaluate(function(username, password) {
            document.querySelector('#email-mini').value = username;
            document.querySelector('#password-mini').value = password;
        }, 'test@thelia.net', 'azerty');

        this.click('form#form-login-mini button[type="submit"]');
    });

    casper.thenOpen(thelia2_base_url + "order/delivery", function() {

        if (this.getCurrentUrl() == thelia2_base_url + "order/invoice") {
            test.info("with a virtual product, the delivery page is skipped");
            test.skip(2);
        } else {
            test.assertTitle("Billing and delivery - Cart - Thelia V2", "title is the one expected for url : " + this.getCurrentUrl());
            this.capture(screenshot_dir + 'front/50_delivery_list.png');

            test.assertEval(function() {
                return __utils__.findAll("#form-cart-delivery table.table-address tr").length >= 1;
            }, "We expect at least one delivery address");

            this.click('form#form-cart-delivery button[type="submit"]');
        }
    });

    casper.waitForSelector(
        'title',
        function(){
            test.assertTitle("My order - Cart - Thelia V2", "title is the one expected for url : " + this.getCurrentUrl());

            test.assertElementCount("table.table-cart tbody tr", 1, "cart contain 1 product");
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
