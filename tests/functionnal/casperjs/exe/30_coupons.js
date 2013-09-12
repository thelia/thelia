casper.test.comment('Testing coupons');


////LIST
// @todo implement

////CREATE
// @todo implement

//UPDATE COUPON RULE
casper.start(thelia2_login_coupon_update_url, function() {
    this.capture('tests/functionnal/casperjs/screenshot/coupons/init.png');
    this.echo('\nCOUPON RULE - EDIT');
    this.test.assertTitle('Update coupon - Thelia Back Office', 'Web page title OK');
//    this.test.assertSelectorHasText('#content-header > h1', 'Liste des pays', 'Web page main content OK');
    this.test.assertSelectorHasText('tbody#constraint-list tr:nth-child(1)', 'If cart total amount is superior to 40 EUR','1st default rule found');
    this.test.assertSelectorHasText('tbody#constraint-list tr:nth-child(2)', 'If cart total amount is inferior to 400 EUR','2nd default rule found');

    // Create rule
    this.evaluate(function() {
        $('#category-rule').val('thelia.constraint.rule.available_for_x_articles').change();
        return true;
    });
    this.capture('tests/functionnal/casperjs/screenshot/coupons/rule-selected.png');
});

casper.wait(1000, function() {
    this.echo("\nWaiting....");
});

casper.then(function(){
    this.evaluate(function() {
        $('#quantity-operator').val('>=').change();
        return true;
    });
    this.sendKeys('input#quantity-value', '4');
    this.click('#constraint-save-btn');
});

casper.wait(1000, function() {
    this.echo("\nWaiting....");
});

casper.then(function(){
    this.capture('tests/functionnal/casperjs/screenshot/coupons/rule-added.png');
    this.test.assertSelectorHasText('tbody#constraint-list tr:nth-child(3)', ' If cart products quantity is superior or equals to 4','3rd rule found');
});

////EDIT CHECK
// @todo implement

////DELETE
// @todo implement

//RUN
casper.run(function() {
    this.test.done();
});