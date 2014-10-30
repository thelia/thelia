var xp = require('casper').selectXPath;

casper.test.comment('== Hook - changing position ==');

casper.test.begin('Front Homepage', 12, function suite(test) {



    var modules = {
        newProducts: {
            // block id in FO
            id: "products-new",
            // title in BO
            title: "Block New Products"
        },
        promoProducts: {
            id: "products-offer",
            title: "Block Promo Products"
        }
    };

    var modulePromoProductId = null;

    // Try to login
    casper.start(thelia2_base_url + 'admin/login', function() {

        test.comment('Login to back Office');

        test.assertTitle("Welcome - Thelia Back Office");
        test.assertExists('div.loginpage', "This is the login page");

        casper.evaluate(function(username, password) {
            document.querySelector('#username').value = username;
            document.querySelector('#password').value = password;
        }, administrator.login, administrator.password);

        this.click('div.loginpage button[type="submit"]');
    });

    casper.waitForSelector('body .homepage', function(){

        test.assertTitle("Back-office home - Thelia Back Office", "This is the dashboard");

    });

    // Get current order
    casper.thenOpen(thelia2_base_url, function() {

        var order = [], i;

        test.comment('Get current order on home page');

        test.assertTitle(thelia2_store_name, "This is the home page : " + this.getTitle());

        homeModules = this.getElementsInfo('#content > section');
        for (i=0 ; i<homeModules.length ; i++){
            order.push(homeModules[i].attributes['id'])
        }

        test.assertTruthy(
            homeModules.length == 2,
            "2 modules on home page : " + order.join(", ")
        );

        test.assertTruthy(
            order.join() == [modules.newProducts.id, modules.promoProducts.id].join(),
            "The order of the module on the home page is good : " + order.join(', ')
        );

    });

    // Change Order
    casper.thenOpen(thelia2_base_url + 'admin/module-hooks', function() {

        test.comment('Change order');

        test.assertTitle("Hooks position - Thelia Back Office", "This is the page to manage modules hooks");

        var linePromoProducts = null;

        linePromoProducts = this.getElementInfo(
            xp('//tr[@class="hook-module"]/td[normalize-space(.)="' + modules.promoProducts.title + '"]/..')
        );

        test.assertTruthy(linePromoProducts != null, "The module Promo Product exist");

        modulePromoProductId = linePromoProducts.attributes['data-module-id'];

        // trigger position change
        this.click('tr[data-module-id="' + modulePromoProductId + '"] a.u-position-up');

        casper.waitFor(
            function(){
                var linePromoProductPosition = this.getElementInfo(
                    'tr[data-module-id="' + modulePromoProductId + '"] .moduleHookPositionChange'
                );
                return linePromoProductPosition.text == "1";
            },
            function(){
                test.comment('Position for hooks position has changed');
            }
        );

    });

    // Get new order
    // Get current order
    casper.thenOpen(thelia2_base_url, function() {

        test.comment('Get new order on home page');

        test.assertTitle(thelia2_store_name, "This is the home page : " + this.getTitle());

        homeModules = this.getElementsInfo('#content > section');
        test.assertTruthy(
            homeModules.length == 2,
            "2 modules on home page"
        );

        order = [
            homeModules[0].attributes['id'],
            homeModules[1].attributes['id']
        ];

        test.assertTruthy(
            order.join() == [modules.promoProducts.id, modules.newProducts.id].join(),
            "The order of the module on the home page has change : " + order.join(', ')
        );

    });

    casper.thenOpen(thelia2_base_url + 'admin/logout', function() {
        test.comment('logout');
        test.assertTitle("Welcome - Thelia Back Office");
    });

    casper.run(function() {
        test.done();
    });

});