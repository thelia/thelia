casper.test.comment('== Register ==');

casper.test.begin('Register', 15, function suite(test) {

    var newEmail = '';

    casper.start(thelia2_base_url + "register", function() {
        test.assertTitle("Register - " + thelia2_store_name, "title is the one expected");
        test.assertExists('form#form-register', "register form is found");
        if (screenshot_enabled) {
            this.capture(screenshot_dir + 'front/20_register.png');
        }

        casper.test.comment('== Register blank account');

        this.fill('form#form-register', {
            'thelia_customer_create[title]': '',
            'thelia_customer_create[firstname]': '',
            'thelia_customer_create[lastname]': '',
            'thelia_customer_create[email]': '',
            'thelia_customer_create[phone]': '',
            'thelia_customer_create[cellphone]': '',
            'thelia_customer_create[company]': '',
            'thelia_customer_create[address1]': '',
            'thelia_customer_create[address2]': '',
            'thelia_customer_create[zipcode]': '',
            'thelia_customer_create[city]': '',
            'thelia_customer_create[country]': '',
            'thelia_customer_create[password]': '',
            'thelia_customer_create[password_confirm]': '',
            'thelia_customer_create[newsletter]': ''
        }, true);

    });


    casper.then(function() {

        casper.test.comment('== Register thelia account');

        if (screenshot_enabled) {
            this.capture(screenshot_dir + 'front/20_register-ko.png');
        }

        test.assertExists('.group-title.has-error', 'title can not be empty');
        test.assertExists('.group-firstname.has-error', 'firstname can not be empty');
        test.assertExists('.group-lastname.has-error', 'lastname can not be empty');
        test.assertExists('.group-email.has-error', 'email can not be empty');
        test.assertExists('.group-address1.has-error', 'address1 can not be empty');
        test.assertExists('.group-zip.has-error', 'zipcode can not be empty');
        test.assertExists('.group-city.has-error', 'city can not be empty');
        test.assertExists('.group-password.has-error', 'password can not be empty');
        test.assertExists('.group-password_confirm.has-error', 'password confirm can not be empty');

        this.fill('form#form-register', {
            'thelia_customer_create[title]': '1',
            'thelia_customer_create[firstname]': 'thelia',
            'thelia_customer_create[lastname]': 'thelia',
            'thelia_customer_create[email]': 'test@thelia.net',
            'thelia_customer_create[email_confirm]': 'test@thelia.net',
            'thelia_customer_create[phone]': '',
            'thelia_customer_create[cellphone]': '',
            'thelia_customer_create[company]': 'OpenStudio',
            'thelia_customer_create[address1]': '4 rue Rochon',
            'thelia_customer_create[address2]': '',
            'thelia_customer_create[zipcode]': '63000',
            'thelia_customer_create[city]': 'Clermont-Ferrand',
            'thelia_customer_create[country]': '64',
            'thelia_customer_create[password]': 'thelia',
            'thelia_customer_create[password_confirm]': 'thelia',
            'thelia_customer_create[newsletter]': ''
        }, false);

        this.click('form#form-register button[type="submit"]');

    });

    casper.waitForSelector(
        '.group-email .help-block',
        function(){
            test.assertSelectorHasText('.group-email .help-block', 'This email already exists.');

            // generate a new email and save it
            // we're going to use this new account later
            newEmail = Math.random().toString(36).substr(2,7) + '@thelia.net';
            thelia_customer.email = newEmail;

            this.fill('form#form-register', {
                'thelia_customer_create[email]': newEmail,
                'thelia_customer_create[email_confirm]': newEmail,
                'thelia_customer_create[password]': 'thelia',
                'thelia_customer_create[password_confirm]': 'thelia'
            }, false);

            this.click('form#form-register button[type="submit"]');

        },
        function(){
            this.die("Selector '.group-email .help-block' not found. It should contain 'This email already exists'");
        },
        thelia_default_timeout
    );

    casper.waitForSelector(
        '.navbar-customer a.account',
        function() {
            if (screenshot_enabled) {
                this.capture(screenshot_dir + 'front/20_register-ok.png');
            }
            test.assertSelectorHasText('.navbar-customer a.account', 'My Account');
            test.assertExists('a.logout', 'Logout button exists');

            casper.test.comment('== Logout');

            this.click('a.logout');
        },
        function(){
            this.die("'My account' button not found");
        },
        thelia_default_timeout
    );

    casper.waitForSelector(
        'a.login',
        function() {
            test.assertExists('a.login', 'Login button exists');
        },
        function() {
            this.die('Login button doesn\'t exist');
        },
        thelia_default_timeout
    );


    casper.run(function() {
        test.done();
    });

});
