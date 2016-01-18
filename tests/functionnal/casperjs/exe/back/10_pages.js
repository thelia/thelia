/**
 * Created by julien on 16/10/14.
 */
var x = require('casper').selectXPath;
var errors = [], resourceErrors = [];

casper.test.begin('Back Office - Pages', 100, function suite(test) {

    var pages = [
        {"url": "admin", "title": "Back-office home"},
        {"url": "admin/home", "title": "Back-office home"},
        {"url": "admin/catalog", "title": "Categories"},
        {"url": "admin/customers", "title": "Customer"},
        {"url": "admin/orders", "title": "Orders"},
        {"url": "admin/categories", "title": "Categories"},
        {"url": "admin/products", "title": "Categories"},
        {"url": "admin/folders", "title": "Folders"},
        {"url": "admin/coupon", "title": "Coupons"},
        {"url": "admin/configuration", "title": "Configuration"},
        {"url": "admin/configuration/variables", "title": "Thelia System Variables"},
        {"url": "admin/configuration/store", "title": "Store"},
        {"url": "admin/configuration/system-logs", "title": "System Logs"},
        {"url": "admin/configuration/messages", "title": "Thelia Mailing Templates"},
        {"url": "admin/configuration/currencies", "title": "Currencies"},
        {"url": "admin/configuration/templates", "title": "Thelia Product Templates"},
        {"url": "admin/configuration/attributes", "title": "Thelia Product Attributes"},
        {"url": "admin/configuration/shipping_zones", "title": "Thelia Shipping zones"},
        {"url": "admin/configuration/countries", "title": "Countries"},
        {"url": "admin/configuration/profiles", "title": "Administration profiles"},
        {"url": "admin/configuration/administrators", "title": "Back-office users"},
        {"url": "admin/configuration/mailingSystem", "title": "Thelia Mailing System"},
        {"url": "admin/configuration/adminLogs", "title": "Administration logs"},
        {"url": "admin/configuration/features", "title": "Thelia Product Features"},
        {"url": "admin/configuration/advanced", "title": "Advanced configuration"},
        {"url": "admin/modules", "title": "Modules"},
        {"url": "admin/hooks", "title": "Hooks"},
        {"url": "admin/module-hooks", "title": "Modules attachments"},
        {"url": "admin/configuration/taxes_rules", "title": "Taxes rules"},
        {"url": "admin/configuration/languages", "title": "Thelia Languages"},
        {"url": "admin/configuration/translations", "title": "Translation"},
        {"url": "admin/brand", "title": "Brands"},
        {"url": "admin/export", "title": "Exports"},
        {"url": "admin/import", "title": "Imports"},
        {"url": "admin/sales", "title": "Sales management"},
        {"url": "admin/logout", "title": "Welcome"}
    ];

    casper.on('page.error', function(msg, trace) {
        this.echo("Error: " + msg, "ERROR");

        errors.push(msg);
    });

    casper.on('resource.error', function(resourceError) {

        var message = [
            "Failed to load resource :", resourceError.url,
            "-", resourceError.errorCode,
            ":", resourceError.errorString
        ].join(' ');

        this.echo(message, "ERROR");

        resourceErrors.push(message);
    });

    casper.start(thelia2_base_url + 'admin/login', function() {

        test.comment('Login to back Office');

        // trick too update the number of planned tests : 100 - 2 - (2x number of urls) !!
        test.skip(98 - (pages.length * 2));


        test.assertTitle("Welcome - Thelia Back Office");
        test.assertExists('body.login-page', "This is the login page");

        if (screenshot_enabled) {
            this.capture(screenshot_dir + "login.png");
        }

        casper.evaluate(function(username, password) {
            document.querySelector('#username').value = username;
            document.querySelector('#password').value = password;
        }, administrator.login, administrator.password);

        this.click('body.login-page button[type="submit"]');

        this.echo('Waiting...');
    });

    casper.then(function(){

        this.echo('Done');
        this.echo('Processing ' + pages.length + ' URLs');

        casper.eachThen(pages, function(response) {

            var page = response.data;

            this.echo('Loading page : ' + page.url);
            casper.thenOpen(thelia2_base_url + page.url);

            casper.waitForSelector("footer.footer",
                function success() {
                    var pageTitle = this.getTitle().replace(" - Thelia Back Office", "");
                    var imageName = page.url + ".png";

                    test.assertExists(
                        "footer.footer",
                        "Page " + page.url + " [" + pageTitle + "] loaded"
                    );
                    test.assertEquals(
                        pageTitle,
                        page.title,
                        "The page title is correct : " + pageTitle
                    );

                    if (screenshot_enabled) {
                        this.capture(screenshot_dir + imageName);
                    }
                },
                function fail() {
                    // try to catch exception
                    if (this.exists('span.exception_title')) {
                        var exceptionTitle = this.fetchText('span.exception_title');
                        var exceptionMessage = this.fetchText('span.exception_message');

                        this.echo(
                            "Exception title : " + exceptionTitle + " " + exceptionMessage,
                            "ERROR"
                        );
                    }

                    test.assertExists(
                        "footer.footer",
                        "Page " + page.url + " can't be loaded !"
                    );
                }
            );

        });

    });

    casper.run(function() {
        test.done();

        if (errors.length > 0) {
            this.echo(errors.length + ' JavaScript errors found', "WARNING");
        } else {
            this.echo('No Javascript errors found', "INFO");
        }

        if (resourceErrors.length > 0) {
            this.echo(resourceErrors.length + ' Resources errors found', "WARNING");
        } else {
            this.echo('No resources errors found', "INFO");
        }

        casper.exit();
    });

});