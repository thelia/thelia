casper.test.comment('Testing coupons');


////LIST
// @todo implement

////CREATE
// @todo implement

//UPDATE COUPON RULE
casper.start(thelia2_login_coupon_update_url, function() {
    console.log('Now on : ' + this.getCurrentUrl());
    this.echo('\nCOUPON RULE - EDIT');
    this.test.assertTitle('Update coupon - Thelia Back Office', 'Web page title OK');
//    this.test.assertSelectorHasText('#content-header > h1', 'Liste des pays', 'Web page main content OK');
    this.test.assertSelectorHasText('tbody#constraint-list tr:nth-child(1)', 'If cart total amount is superior to 40 EUR','1st default rule found');
    this.test.assertSelectorHasText('tbody#constraint-list tr:nth-child(2)', 'If cart total amount is inferior to 400 EUR','2nd default rule found');

    // Create rule
    this.evaluate(function() {
//        document.querySelector('select#category-rule').selectedItem = 'thelia.constraint.rule.available_for_x_articles';
        $('#category-rule').val('thelia.constraint.rule.available_for_x_articles').change();
        return true;
    });
    this.capture('screenshot-category-rule.png');
//    this.click('constraint-list > tr:last-child > td > a.constraint-update-btn');
});


////EDIT CHECK
// @todo implement

////DELETE
// @todo implement

//RUN
casper.run(function() {
    this.test.done();
});