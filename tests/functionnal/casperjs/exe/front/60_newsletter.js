casper.test.comment('== Newsletter ==');

casper.test.begin('Newsletter', 12, function suite(test) {

    var newEmail = '';

    casper.start(thelia2_base_url + "newsletter", function() {
        test.assertTitle("Newsletter - Thelia V2", "title is the one expected");

        test.assertExists('.navbar-customer .register', 'user is disconnected');

        test.assertExists('form#form-newsletter', "newsletter form is found");

        casper.test.comment("== User isn't connected");

        test.assertExists('form#form-newsletter #firstname_newsletter', 'firstname field is displayed');
        test.assertExists('form#form-newsletter #lastname_newsletter', 'lastname field is displayed');

        this.capture(screenshot_dir + 'front/60_newsletter.png');

        casper.test.comment('== Newsletter blank submission');

        this.fill('form#form-newsletter', {
            'thelia_newsletter[email]': '',
            'thelia_newsletter[firstname]': 'Thelia',
            'thelia_newsletter[lastname]': 'Thelia'
        }, true);

    });


    casper.then(function() {

        casper.evaluate(function(email) {
            document.querySelector('#email_newsletter').value = email;
        }, '');

        this.click('form#form-newsletter button[type="submit"]');

    });

    casper.wait(thelia_default_timeout, function(){
        this.capture(screenshot_dir + 'front/60_newsletter-ko-0.png');
        test.assertExists('form#form-newsletter .group-email.has-error', 'email can not be empty');
    });

    casper.wait(thelia_default_timeout, function(){

        casper.test.comment('== Existing email on submission');

        this.fill('form#form-newsletter', {
            'thelia_newsletter[email]': 'test@thelia.net',
            'thelia_newsletter[firstname]': 'Thelia',
            'thelia_newsletter[lastname]': 'Thelia'
        }, true);

        casper.evaluate(function(email) {
            document.querySelector('#email_newsletter').value = email;
        }, 'test@thelia.net');

        this.click('form#form-newsletter button[type="submit"]');
    });

    casper.wait(thelia_default_timeout, function(){
        this.capture(screenshot_dir + 'front/60_newsletter-ko-0.png');
        test.assertExists('form#form-newsletter .group-email.has-error', 'email already exist');
    });

    casper.wait(thelia_default_timeout, function(){

        casper.test.comment('== Great email on submission');

        newEmail = Math.random().toString(36).substr(2,7) + '@thelia.net';

        this.fill('form#form-newsletter', {
            'thelia_newsletter[email]': newEmail,
            'thelia_newsletter[firstname]': 'Thelia',
            'thelia_newsletter[lastname]': 'Thelia'
        }, true);

        casper.evaluate(function(email) {
            document.querySelector('#email_newsletter').value = email;
        }, newEmail);

        this.click('form#form-newsletter button[type="submit"]');
    });

    casper.wait(thelia_default_timeout, function(){
        this.capture(screenshot_dir + 'front/60_newsletter-ok-0.png');
        test.assertExists('form#form-newsletter .group-email.has-success', 'subscription with success');
    });

    casper.wait(thelia_default_timeout, function(){

        casper.test.comment('== Login user');

        casper.evaluate(function(username, password) {
            document.querySelector('#email-mini').value = username;
            document.querySelector('#password-mini').value = password;
        }, 'test@thelia.net', 'azerty');

        this.click('form#form-login-mini button[type="submit"]');
    });

    casper.wait(thelia_default_timeout, function(){
        this.capture(screenshot_dir + 'front/60_newsletter-ok-1.png');
        test.assertExists('a.logout', 'Logout button exists');
        test.assertDoesntExist('form#form-newsletter #firstname_newsletter', "firstname field doesn't exist");
        test.assertDoesntExist('form#form-newsletter #lastname_newsletter', "lastname field doesn't exist");
    });

    casper.wait(thelia_default_timeout, function(){

        casper.test.comment('== Subscribe again');

        this.fill('form#form-newsletter', {
            'thelia_newsletter[email]': 'test@thelia.net'
        }, true);

        casper.evaluate(function(email) {
            document.querySelector('#email_newsletter').value = email;
        }, 'test@thelia.net');

        this.click('form#form-newsletter button[type="submit"]');
    });

    casper.wait(thelia_default_timeout, function(){
        this.capture(screenshot_dir + 'front/60_newsletter-ko-1.png');
        test.assertExists('form#form-newsletter .group-email.has-error', 'this user is already registered');
    });

    casper.run(function() {
        test.done();
    });

});