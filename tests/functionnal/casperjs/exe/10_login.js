casper.test.comment('Testing login');

casper.start(thelia2_login_admin_url, function() {
    this.echo('\nLOGIN');
    this.test.assertTitle('Welcome - Thelia Back Office', 'Web page title OK');
    this.sendKeys('input#username', 'thelia2');
    this.sendKeys('input#password', 'thelia2');
    this.click('form[action*="checklogin"] button[type="submit"]');
});

casper.wait(1000, function() {
    this.echo("\nWaiting....");
});

casper.then(function(){
    this.echo('\nDASHBOARD');

    console.log('Now on : ' + this.getCurrentUrl());
    // @todo implement dashboard
//    this.test.assertTitle('Back-office home - Thelia Back Office', 'Web page title OK');
//    this.test.assertSelectorHasText('#wrapper > div', ' 			This is the administration home page. Put some interesting statistics here, and display useful information :) 			', 'Web page main content OK');
});

//RUN
casper.run(function() {
    this.test.done();
});