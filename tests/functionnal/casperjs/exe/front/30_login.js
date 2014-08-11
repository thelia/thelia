casper.test.comment('== Login ==');

casper.test.begin('Login', 5, function suite(test) {

    casper.start(thelia2_base_url + "login", function() {
        test.assertTitle("Login - Thelia V2", "title is the one expected");
        test.assertExists('form#form-login', "login form is found");
        this.capture(screenshot_dir + 'front/30_login.png');

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

        this.capture(screenshot_dir + 'front/30_login-ko-0.png');

        this.click('form#form-login button[type="submit"]');

    });

    casper.wait(2000, function(){

        this.capture(screenshot_dir + 'front/30_login-ko.png');

        test.assertSelectorHasText('form#form-login .alert-danger', 'Wrong email or password. Please try again');

        casper.test.comment('== Login with a good account');

        casper.evaluate(function(username, password) {
            document.querySelector('#email').value = username;
            document.querySelector('#password').disabled = false;
            document.querySelector('#password').value = password;
        }, 'test@thelia.net', 'azerty');

        this.click('form#form-login button[type="submit"]');
    });


    casper.wait(2000, function(){

        this.capture(screenshot_dir + 'front/30_login-ok.png');
        test.assertExists('a.logout', 'Logout button exists');

        casper.test.comment('== Logout');

        this.click('a.logout');
    });

    casper.wait(2000, function(){

        test.assertExists('a.login', 'Login button exists');

    });


    casper.run(function() {
        test.done();
    });

});