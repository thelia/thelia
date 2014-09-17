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

        test.assertTitle("Billing and delivery - Cart - Thelia V2", "title is the one expected");
        this.capture(screenshot_dir + 'front/50_delivery_list.png');

        test.assertEval(function() {
            return __utils__.findAll("#form-cart-delivery table.table-address tr").length >= 1;
        }, "We expect at least one delivery address");

        this.click('form#form-cart-delivery button[type="submit"]');
    });

    casper.wait(thelia_default_timeout, function(){
        test.assertTitle("My order - Cart - Thelia V2", "title is the one expected");

        test.assertElementCount("table.table-cart tbody tr", 1, "cart contain 1 product");
    });

    casper.run(function() {
        test.done();
    });
});
