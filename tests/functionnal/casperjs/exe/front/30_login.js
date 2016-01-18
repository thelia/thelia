casper.test.comment('== Login ==');

casper.test.begin('Login', 5, function suite(test) {

    casper.start(thelia2_base_url + "login", function() {
        test.assertTitle("Login - " + thelia2_store_name, "title is the one expected");
        test.assertExists('form#form-login', "login form is found");
        if (screenshot_enabled) {
            this.capture(screenshot_dir + 'front/30_login.png');
        }
        casper.test.comment('== Login with a bad account');

        this.fill('form#form-login', {
            'thelia_customer_login[account]': "1"
        }, false);

    });

    casper.then(function(){

        casper.evaluate(function(username, password) {
            document.querySelector('#email').value = username;
            document.querySelector('#password').disabled = false;
            document.querySelector('#password').value = password;
        }, 'chuck-norris@thelia.net', 'thelia');

        if (screenshot_enabled) {
            this.capture(screenshot_dir + 'front/30_login-ko-0.png');
        }

        this.click('form#form-login button[type="submit"]');

    });

    casper.waitForSelector(
        'form#form-login .alert-danger',
        function(){
            if (screenshot_enabled) {
                this.capture(screenshot_dir + 'front/30_login-ko.png');
            }

            test.assertSelectorHasText('form#form-login .alert-danger', 'Wrong email or password. Please try again');

            casper.test.comment('== Login with a good account');

            casper.evaluate(function(username, password) {
                document.querySelector('#email').value = username;
                document.querySelector('#password').disabled = false;
                document.querySelector('#password').value = password;
                document.querySelector('#remerber_me').checked = false;
            }, 'test@thelia.net', 'azerty');

            this.click('form#form-login button[type="submit"]');
        },
        function(){
            this.die("Selector 'form#form-login .alert-danger' not found. It should contain the message 'Wrong email or password. Please try again'");
        },
        thelia_default_timeout
    );


    casper.waitForSelector(
        'a.logout',
        function(){
            if (screenshot_enabled) {
                this.capture(screenshot_dir + 'front/30_login-ok.png');
            }
            test.assertExists('a.logout', 'Logout button exists');

            casper.test.comment('== Logout');

            this.click('a.logout');
        },
        function(){
            this.die("Logout button not found");
        },
        thelia_default_timeout
    );

    casper.waitForSelector(
        'a.login',
        function() {
            test.assertExists('a.login', 'Login button exists');

            casper.evaluate(function(username, password) {
                document.querySelector('#email-mini').value = username;
                document.querySelector('#password-mini').value = password;
            }, thelia_customer.email, thelia_customer.password);
            this.click('form#form-login-mini button[type="submit"]');
        },
        function() {
            this.die('Login button not found');
        },
        thelia_default_timeout
    );


    casper.run(function() {
        test.done();
    });

});