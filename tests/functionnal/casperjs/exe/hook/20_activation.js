var xp = require('casper').selectXPath;

casper.test.comment('== Hook - changing position ==');

casper.test.begin('Front Homepage', 22, function suite(test) {

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
        },
        blockCurrency: {
            id: 'nav-currency',
            title: 'Block Currency'
        }
    };

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

    // be sure to have the good configuration

    casper.thenOpen(thelia2_base_url, function() {

        test.comment('Get current configuration on home page');

        test.assertTitle(thelia2_store_name, "This is the home page : " + this.getTitle());

        // 2 modules in hook home.body
        test.assertElementCount(
            '#content > section',
            2,
            "2 modules on home page (hook: home.body)"
        );

        // module currency exists
        test.assertExists(
            ".nav-secondary .navbar-currency",
            "Module Currency selector exists"
        );

        // module currency exists
        test.assertExists(
            ".nav-secondary .navbar-currency",
            "Module Currency selector exists"
        );

    });

    // deactivation of module hook

    casper.thenOpen(thelia2_base_url + 'admin/module-hooks', function() {

        test.comment('deactivate module hook for Promo products');

        test.assertTitle("Hooks position - Thelia Back Office", "This is the page to manage modules hooks");

        var moduleHookTag = null,
            hookId = null;

        moduleHookTag = this.getElementInfo(
            xp('//tr[@class="hook-module"]/td[normalize-space(.)="' + modules.promoProducts.title + '"]/..')
        );

        test.assertTruthy(moduleHookTag != null, "The module Promo Product exist");

        hookId = moduleHookTag.attributes['data-module-id'];

        // test if activated
        test.assertExist(
            'tr[data-module-id="' + hookId + '"] .switch-on',
            'module hook for Promo products is activated'
        );
        // trigger toggle activation
        this.click('tr[data-module-id="' + hookId + '"] div.module-hook-activation .switch-left');

        test.comment('waiting...');

        casper.waitForSelector('tr[data-module-id="' + hookId + '"] .switch-off', function(){
            test.comment('Status for hook position has changed');
        });

    });

    // deactivation of module Block Currency

    casper.thenOpen(thelia2_base_url + 'admin/modules', function() {

        var moduleIdTag = null,
            moduleId = null;

        test.comment('deactivate module Block Currency');

        test.assertTitle("Modules - Thelia Back Office", "This is the page to manage modules hooks");

        moduleIdTag = this.getElementInfo(
            xp('//tr/td[normalize-space(.)="' + modules.blockCurrency.title + '"]/../td[1]')
        );

        test.assertTruthy(moduleIdTag != null, "The module Block Currency exists");

        moduleId = moduleIdTag.text;

        // test if activated
        test.assertExist(
            '.make-switch[data-id="' + moduleId + '"] .switch-on',
            'module Block Currency is activated'
        );

        // trigger toggle activation
        this.click('.make-switch[data-id="' + moduleId + '"] .switch-left');

        test.comment('waiting...');

        casper.waitForSelector('.make-switch[data-id="' + moduleId + '"] .switch-off', function(){
            test.comment('Status for module has changed');
        });
    });

    // deactivation of hook main.navbar-primary

    casper.thenOpen(thelia2_base_url + 'admin/hooks', function() {

        test.comment('deactivate hook main.navbar-primary');

        test.assertTitle("Hooks - Thelia Back Office", "This is the page to manage modules hooks");

        var hookTag = null,
            hookId = null;

        hookTag = this.getElementInfo(
            xp('//tr/td/a[normalize-space(.)="main.navbar-primary"]/../../td[1]')
        );

        test.assertTruthy(hookTag != null, "The hook main.navbar-primary exists");

        hookId = hookTag.text;

        // test if activated
        test.assertExist(
            '.make-switch[data-id="' + hookId + '"] .switch-on',
            'hook main.navbar-primary is activated'
        );
        // trigger toggle activation
        this.click('.make-switch[data-id="' + hookId + '"] .switch-left');

        test.comment('waiting...');

        casper.waitForSelector('.make-switch[data-id="' + hookId + '"] .switch-off', function(){
            test.comment('Status for hook has changed');
        });
    });

    // Test the new home page
    casper.thenOpen(thelia2_base_url, function() {

        test.comment('Get new configuration on home page');

        test.assertTitle(thelia2_store_name, "This is the home page : " + this.getTitle());

        // 1 module in hook home.body
        test.assertElementCount(
            '#content > section',
            1,
            "1 module on home page"
        );

        test.assertDoesntExist(
            modules.promoProducts.id,
            "The module on the home page is ok"
        );

        // module currency should not exist
        test.assertDoesntExist(
            ".nav-secondary .navbar-currency",
            "Module Currency selector doesn't exist"
        );

        // module currency exists
        test.assertDoesntExist(
            "header.container .nav-main",
            "Main navigation doesn't exists"
        );

    });

    casper.thenOpen(thelia2_base_url + 'admin/logout', function() {
        test.comment('logout');
        test.assertTitle("Welcome - Thelia Back Office", "This is the good title. url: " + this.getCurrentUrl());
    });

    casper.run(function() {
        test.done();
    });

});