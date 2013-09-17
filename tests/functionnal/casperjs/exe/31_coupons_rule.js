//
//var casper = require('casper').create({
//    viewportSize:{
//        width:1024, height:768
//    },
//    pageSettings:{
//        userAgent:'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_7_2) AppleWebKit/535.11 (KHTML, like Gecko) Chrome/17.0.963.79 Safari/535.11'
//    },
//    verbose:true
//});

casper.test.comment('Testing coupons rules');

//UPDATE COUPON RULE
casper.start(thelia2_login_coupon_update_url, function() {
    this.test.assertHttpStatus(200);
    this.test.comment('Now on : ' + this.getCurrentUrl());
    this.capture('tests/functionnal/casperjs/screenshot/coupons/init.png');
    this.test.comment('COUPON RULE - EDIT');
    this.test.assertTitle('Update coupon - Thelia Back Office', 'Web page title OK');
    this.test.assertSelectorHasText('tbody#constraint-list tr:nth-child(1)', 'If cart total amount is superior to 40 EUR','1) 1st default rule found');
    this.test.assertSelectorHasText('tbody#constraint-list tr:nth-child(2)', 'If cart total amount is inferior to 400 EUR','1) 2nd default rule found');

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
    this.test.assertSelectorHasText('tbody#constraint-list tr:nth-child(1)', 'If cart total amount is superior to 40 EUR','2) 1st default rule found');
    this.test.assertSelectorHasText('tbody#constraint-list tr:nth-child(2)', 'If cart total amount is inferior to 400 EUR','2) 2nd default rule found');
    this.test.assertSelectorHasText('tbody#constraint-list tr:nth-child(3)', ' If cart products quantity is superior or equal to 4','2) 3rd rule found');

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

    // Removing old value
//    casper.evaluate(function triggerKeyDownEvent() {
//        var e = $.Event("keydown");
//        e.which = 8;
//        e.keyCode = 8;
//        $("#quantity-value").trigger(e);
//    });
    this.evaluate(function() {
        $("#quantity-value").val('').change();
        return true;
    });

    // Adding new value
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
    this.test.assertSelectorHasText('tbody#constraint-list tr:nth-child(1)', 'If cart total amount is superior to 40 EUR','3) 1st default rule found');
    this.test.assertSelectorHasText('tbody#constraint-list tr:nth-child(2)', 'If cart total amount is inferior to 400 EUR','3) 2nd default rule found');
    this.test.assertSelectorHasText('tbody#constraint-list tr:nth-child(3)', 'If cart products quantity is equal to 5','3) 3rd rule updated found');
});

// Check if updated rule has been well saved
casper.thenOpen(thelia2_login_coupon_update_url, function() {
    this.test.assertHttpStatus(200);
    this.test.comment('Now on : ' + this.getCurrentUrl());
    this.capture('tests/functionnal/casperjs/screenshot/coupons/rule-edited-refreshed.png');
    this.test.assertSelectorHasText('tbody#constraint-list tr:nth-child(1)', 'If cart total amount is superior to 40 EUR','4) 1st default rule found');
    this.test.assertSelectorHasText('tbody#constraint-list tr:nth-child(2)', 'If cart total amount is inferior to 400 EUR','4) 2nd default rule found');
    this.test.assertSelectorHasText('tbody#constraint-list tr:nth-child(3)', 'If cart products quantity is equal to 5','4) 3rd rule updated found');

    // Click on Delete button
    this.click('tbody#constraint-list tr:nth-child(2) .constraint-delete-btn');
});

casper.wait(2000, function() {
    this.echo("\nWaiting....");
});

casper.then(function(){
    this.test.assertSelectorHasText('tbody#constraint-list tr:nth-child(1)', 'If cart total amount is superior to 40 EUR','5) 1st default rule found');
    this.test.assertSelectorDoesntHaveText('tbody#constraint-list tr:nth-child(2)', 'If cart total amount is inferior to 400 EUR','5) 2nd default rule found');
    this.test.assertSelectorHasText('tbody#constraint-list tr:nth-child(2)', 'If cart products quantity is equal to 5','5) 3rd rule updated found');
});

// Check if updated rule has been well saved
casper.thenOpen(thelia2_login_coupon_update_url, function() {
    this.test.assertHttpStatus(200);
    this.test.comment('Now on : ' + this.getCurrentUrl());
    this.capture('tests/functionnal/casperjs/screenshot/coupons/rule-deleted-refreshed.png');
    this.test.assertSelectorHasText('tbody#constraint-list tr:nth-child(1)', 'If cart total amount is superior to 40 EUR','6) 1st default rule found');
    this.test.assertSelectorDoesntHaveText('tbody#constraint-list tr:nth-child(2)', 'If cart total amount is inferior to 400 EUR','6) 2nd default rule found');
    this.test.assertSelectorHasText('tbody#constraint-list tr:nth-child(2)', 'If cart products quantity is equal to 5','6) 3rd rule updated found');
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

casper.wait(2000, function() {
    this.echo("\nWaiting....");
});

// Test Rule creation
casper.then(function(){
    this.evaluate(function() {
        $('#price-operator').val('<=').change();
        return true;
    });
    // Removing old value
//    casper.evaluate(function triggerKeyDownEvent() {
//        var e = $.Event("keydown");
//        e.which = 8;
//        e.keyCode = 8;
//        $("input#price-value").trigger(e);
//    });
    this.evaluate(function() {
        $("input#price-value").val('').change();
        return true;
    });

    // Changing 400 to 401
    this.sendKeys('input#price-value', '401');
    this.evaluate(function() {
        $('#currency-value').val('GBP').change();
        return true;
    });
    this.capture('tests/functionnal/casperjs/screenshot/coupons/rule-saved-edited-before-click-save.png');
    this.click('#constraint-save-btn');
});

casper.wait(2000, function() {
    this.echo("\nWaiting....");
});

casper.then(function(){
    this.test.assertSelectorHasText('tbody#constraint-list tr:nth-child(1)', 'If cart total amount is superior to 40 EUR','7) 1st default rule found');
    this.test.assertSelectorDoesntHaveText('tbody#constraint-list tr:nth-child(2)', 'If cart total amount is inferior to 400 EUR','7) 2nd default rule found');
    this.test.assertSelectorHasText('tbody#constraint-list tr:nth-child(2)', 'If cart products quantity is equal to 5','7) 3rd rule updated found');
    this.test.assertSelectorHasText('tbody#constraint-list tr:nth-child(3)', 'If cart total amount is inferior or equal to 401 GBP','7) 4rd rule created found');
});

// Check if created rule has been well saved
casper.thenOpen(thelia2_login_coupon_update_url, function() {
    this.test.assertHttpStatus(200);
    this.test.comment('Now on : ' + this.getCurrentUrl());
    this.capture('tests/functionnal/casperjs/screenshot/coupons/rule-added-refreshed.png');
    this.test.assertSelectorHasText('tbody#constraint-list tr:nth-child(1)', 'If cart total amount is superior to 40 EUR','8) 1st default rule found');
    this.test.assertSelectorDoesntHaveText('tbody#constraint-list tr:nth-child(2)', 'If cart total amount is inferior to 400 EUR','8) 2nd default rule found');
    this.test.assertSelectorHasText('tbody#constraint-list tr:nth-child(2)', 'If cart products quantity is equal to 5','8) 3rd rule updated found');
    this.test.assertSelectorHasText('tbody#constraint-list tr:nth-child(3)', 'If cart total amount is inferior or equal to 401 GBP','8) 4rd rule created found');
});

// Testing deleting all rules
casper.then(function(){
// Click on Delete button
    this.click('tbody#constraint-list tr:nth-child(1) .constraint-delete-btn');
});
casper.wait(1000, function() {
    this.echo("\nWaiting....");
});
casper.then(function(){
// Click on Delete button
    this.click('tbody#constraint-list tr:nth-child(1) .constraint-delete-btn');
});
casper.wait(1000, function() {
    this.echo("\nWaiting....");
});
casper.then(function(){
// Click on Delete button
    this.click('tbody#constraint-list tr:nth-child(1) .constraint-delete-btn');
});
casper.wait(1000, function() {
    this.echo("\nWaiting....");
});
casper.then(function(){
    this.capture('tests/functionnal/casperjs/screenshot/coupons/rule-all-deleted.png');
    this.test.assertSelectorHasText('tbody#constraint-list tr:nth-child(1)', 'No conditions','9) 1st default rule found');
    test.assertDoesntExist('tbody#constraint-list tr:nth-child(2)');
});

// Check if created rule has been well saved
casper.thenOpen(thelia2_login_coupon_update_url, function() {
    this.test.assertHttpStatus(200);
    this.test.comment('Now on : ' + this.getCurrentUrl());
    this.capture('tests/functionnal/casperjs/screenshot/coupons/rule-all-deleted-refreshed.png');
    this.test.assertSelectorHasText('tbody#constraint-list tr:nth-child(1)', 'No conditions','10) 1st default rule found');
    test.assertDoesntExist('tbody#constraint-list tr:nth-child(2)');
});


// Test add no condition rule
casper.then(function(){
    this.evaluate(function() {
        $('#category-rule').val('thelia.constraint.rule.available_for_x_articles').change();
        return true;
    });
});

casper.wait(1000, function() {
    this.echo("\nWaiting....");
});

// Test Rule updating
casper.then(function(){
    this.evaluate(function() {
        $('#quantity-operator').val('>').change();
        return true;
    });
    this.sendKeys('input#quantity-value', '4');
    this.click('#constraint-save-btn');
});

casper.wait(1000, function() {
    this.echo("\nWaiting....");
});

casper.then(function(){
    this.capture('tests/functionnal/casperjs/screenshot/coupons/rule-all-deleted.png');
    this.test.assertSelectorHasText('tbody#constraint-list tr:nth-child(1)', 'If cart products quantity is superior to 4', '11) 1st default rule found');
    test.assertDoesntExist('tbody#constraint-list tr:nth-child(2)');
});

// Check if created rule has been well saved
casper.thenOpen(thelia2_login_coupon_update_url, function() {
    this.test.assertHttpStatus(200);
    this.test.comment('Now on : ' + this.getCurrentUrl());
    this.capture('tests/functionnal/casperjs/screenshot/coupons/rule-all-deleted-refreshed.png');
    this.test.assertSelectorHasText('tbody#constraint-list tr:nth-child(1)', 'If cart products quantity is superior to 4','12) 1st default rule found');
    test.assertDoesntExist('tbody#constraint-list tr:nth-child(2)');
});

casper.then(function(){
    this.evaluate(function() {
        $('#category-rule').val('thelia.constraint.rule.available_for_everyone').change();
        return true;
    });
});

casper.wait(1000, function() {
    this.echo("\nWaiting....");
});

// Test Rule updating
casper.then(function(){
    this.click('#constraint-save-btn');
});

casper.wait(1000, function() {
    this.echo("\nWaiting....");
});
casper.then(function(){
    this.capture('tests/functionnal/casperjs/screenshot/coupons/rule-all-deleted.png');
    this.test.assertSelectorHasText('tbody#constraint-list tr:nth-child(1)', 'No conditions','13) 1st default rule found');
    test.assertDoesntExist('tbody#constraint-list tr:nth-child(2)');
});

// Check if created rule has been well saved
casper.thenOpen(thelia2_login_coupon_update_url, function() {
    this.test.assertHttpStatus(200);
    this.test.comment('Now on : ' + this.getCurrentUrl());
    this.capture('tests/functionnal/casperjs/screenshot/coupons/rule-all-deleted-refreshed.png');
    this.test.assertSelectorHasText('tbody#constraint-list tr:nth-child(1)', 'No conditions','14) 1st default rule found');
    test.assertDoesntExist('tbody#constraint-list tr:nth-child(2)');
});

//RUN
casper.run(function() {
    this.test.done();
});