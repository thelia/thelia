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

// Test Rule updating
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
    this.test.assertSelectorHasText('tbody#constraint-list tr:nth-child(1)', 'If cart total amount is superior to 40 EUR','1st default rule found');
    this.test.assertSelectorHasText('tbody#constraint-list tr:nth-child(2)', 'If cart total amount is inferior to 400 EUR','2nd default rule found');
    this.test.assertSelectorHasText('tbody#constraint-list tr:nth-child(3)', ' If cart products quantity is superior or equals to 4','3rd rule found');

    // Click on Edit button
    this.click('tbody#constraint-list tr:nth-child(3) .constraint-update-btn');
});

casper.wait(2000, function() {
    this.echo("\nWaiting....");
});

casper.then(function(){
    this.evaluate(function() {
        $('#quantity-operator').val('==').change();
        return true;
    });
    this.sendKeys('#quantity-value', '5');
    this.capture('tests/functionnal/casperjs/screenshot/coupons/rule-being-edited.png');
    this.click('#constraint-save-btn');
});

casper.wait(2000, function() {
    this.echo("\nWaiting....");
});
// Check if updated rule has been saved and list refreshed
casper.then(function(){
    this.capture('tests/functionnal/casperjs/screenshot/coupons/rule-edited.png');
    this.test.assertSelectorHasText('tbody#constraint-list tr:nth-child(1)', 'If cart total amount is superior to 40 EUR','1st default rule found');
    this.test.assertSelectorHasText('tbody#constraint-list tr:nth-child(2)', 'If cart total amount is inferior to 400 EUR','2nd default rule found');
    this.test.assertSelectorHasText('tbody#constraint-list tr:nth-child(3)', 'If cart products quantity is equals to 5','3rd rule updated found');
});

// Check if updated rule has been well saved
casper.start(thelia2_login_coupon_update_url, function() {
    this.capture('tests/functionnal/casperjs/screenshot/coupons/rule-edited-refreshed.png');
    this.test.assertSelectorHasText('tbody#constraint-list tr:nth-child(1)', 'If cart total amount is superior to 40 EUR','1st default rule found');
    this.test.assertSelectorHasText('tbody#constraint-list tr:nth-child(2)', 'If cart total amount is inferior to 400 EUR','2nd default rule found');
    this.test.assertSelectorHasText('tbody#constraint-list tr:nth-child(3)', 'If cart products quantity is equals to 5','3rd rule updated found');

    // Click on Delete button
    this.click('tbody#constraint-list tr:nth-child(2) .constraint-delete-btn');
    this.test.assertSelectorHasText('tbody#constraint-list tr:nth-child(1)', 'If cart total amount is superior to 40 EUR','1st default rule found');
    this.test.assertSelectorDoesntHaveText('tbody#constraint-list tr:nth-child(2)', 'If cart total amount is inferior to 400 EUR','2nd default rule found');
    this.test.assertSelectorHasText('tbody#constraint-list tr:nth-child(3)', 'If cart products quantity is equals to 5','3rd rule updated found');
});

// Check if updated rule has been well saved
casper.start(thelia2_login_coupon_update_url, function() {
    this.capture('tests/functionnal/casperjs/screenshot/coupons/rule-deleted-refreshed.png');
    this.test.assertSelectorHasText('tbody#constraint-list tr:nth-child(1)', 'If cart total amount is superior to 40 EUR','1st default rule found');
    this.test.assertSelectorDoesntHaveText('tbody#constraint-list tr:nth-child(2)', 'If cart total amount is inferior to 400 EUR','2nd default rule found');
    this.test.assertSelectorHasText('tbody#constraint-list tr:nth-child(3)', 'If cart products quantity is equals to 5','3rd rule updated found');
});

// Test creating rule that won't be edited
casper.then(function(){
// Create rule
    this.evaluate(function() {
        $('#category-rule').val('thelia.constraint.rule.available_for_total_amount').change();
        return true;
    });
    this.capture('tests/functionnal/casperjs/screenshot/coupons/rule-selected2.png');
});

casper.wait(1000, function() {
    this.echo("\nWaiting....");
});

// Test Rule creation
casper.then(function(){
    this.evaluate(function() {
        $('#price-operator').val('<=').change();
        return true;
    });
    this.sendKeys('input#price-value', '401');
    this.evaluate(function() {
        $('#currency-value').val('GBP').change();
        return true;
    });
    this.click('#constraint-save-btn');
});

casper.wait(1000, function() {
    this.echo("\nWaiting....");
});

casper.then(function(){
    this.test.assertSelectorHasText('tbody#constraint-list tr:nth-child(1)', 'If cart total amount is superior to 40 EUR','1st default rule found');
    this.test.assertSelectorDoesntHaveText('tbody#constraint-list tr:nth-child(2)', 'If cart total amount is inferior to 400 EUR','2nd default rule found');
    this.test.assertSelectorHasText('tbody#constraint-list tr:nth-child(2)', 'If cart products quantity is equals to 5','3rd rule updated found');
    this.test.assertSelectorHasText('tbody#constraint-list tr:nth-child(3)', 'If cart total amount is inferior or equals to 401 GBP','4rd rule created found');
});

// Check if created rule has been well saved
casper.start(thelia2_login_coupon_update_url, function() {
    this.capture('tests/functionnal/casperjs/screenshot/coupons/rule-added-refreshed.png');
    this.test.assertSelectorHasText('tbody#constraint-list tr:nth-child(1)', 'If cart total amount is superior to 40 EUR','1st default rule found');
    this.test.assertSelectorDoesntHaveText('tbody#constraint-list tr:nth-child(2)', 'If cart total amount is inferior to 400 EUR','2nd default rule found');
    this.test.assertSelectorHasText('tbody#constraint-list tr:nth-child(2)', 'If cart products quantity is equals to 5','3rd rule updated found');
    this.test.assertSelectorHasText('tbody#constraint-list tr:nth-child(3)', 'If cart total amount is inferior or equals to 401 GBP','4rd rule created found');
});

////EDIT CHECK
// @todo implement

////DELETE
// @todo implement

//RUN
casper.run(function() {
    this.test.done();
});