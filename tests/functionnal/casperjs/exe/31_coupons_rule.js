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

casper.test.comment('Testing coupons conditions');

//UPDATE COUPON CONDITION
casper.start(thelia2_login_coupon_update_url, function() {
    this.test.assertHttpStatus(200);
    this.test.comment('Now on : ' + this.getCurrentUrl());
    this.capture('tests/functionnal/casperjs/screenshot/coupons/init.png');
    this.test.comment('COUPON CONDITION - EDIT');
    this.test.assertTitle('Update coupon - Thelia Back Office', 'Web page title OK');
    this.test.assertSelectorHasText('tbody#condition-list tr:nth-child(1)', 'If cart total amount is superior to 40 EUR','1) 1st default condition found');
    this.test.assertSelectorHasText('tbody#condition-list tr:nth-child(2)', 'If cart total amount is inferior to 400 EUR','1) 2nd default condition found');

    // Create condition
    this.evaluate(function() {
        $('#category-condition').val('thelia.condition.match_for_x_articles').change();
        return true;
    });
    this.capture('tests/functionnal/casperjs/screenshot/coupons/condition-selected.png');
});

casper.wait(1000, function() {
    this.echo("\nWaiting....");
});

// Test Condition updating
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
    this.capture('tests/functionnal/casperjs/screenshot/coupons/condition-added.png');
    this.test.assertSelectorHasText('tbody#condition-list tr:nth-child(1)', 'If cart total amount is superior to 40 EUR','2) 1st default condition found');
    this.test.assertSelectorHasText('tbody#condition-list tr:nth-child(2)', 'If cart total amount is inferior to 400 EUR','2) 2nd default condition found');
    this.test.assertSelectorHasText('tbody#condition-list tr:nth-child(3)', ' If cart products quantity is superior or equal to 4','2) 3rd condition found');

    // Click on Edit button
    this.click('tbody#constraint-list tr:nth-child(3) .condition-update-btn');
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
    this.capture('tests/functionnal/casperjs/screenshot/coupons/condition-being-edited.png');
    this.click('#constraint-save-btn');
});

casper.wait(2000, function() {
    this.echo("\nWaiting....");
});
// Check if updated condition has been saved and list refreshed
casper.then(function(){
    this.capture('tests/functionnal/casperjs/screenshot/coupons/condition-edited.png');
    this.test.assertSelectorHasText('tbody#condition-list tr:nth-child(1)', 'If cart total amount is superior to 40 EUR','3) 1st default condition found');
    this.test.assertSelectorHasText('tbody#condition-list tr:nth-child(2)', 'If cart total amount is inferior to 400 EUR','3) 2nd default condition found');
    this.test.assertSelectorHasText('tbody#condition-list tr:nth-child(3)', 'If cart products quantity is equal to 5','3) 3rd condition updated found');
});

// Check if updated condition has been well saved
casper.thenOpen(thelia2_login_coupon_update_url, function() {
    this.test.assertHttpStatus(200);
    this.test.comment('Now on : ' + this.getCurrentUrl());
    this.capture('tests/functionnal/casperjs/screenshot/coupons/condition-edited-refreshed.png');
    this.test.assertSelectorHasText('tbody#condition-list tr:nth-child(1)', 'If cart total amount is superior to 40 EUR','4) 1st default condition found');
    this.test.assertSelectorHasText('tbody#condition-list tr:nth-child(2)', 'If cart total amount is inferior to 400 EUR','4) 2nd default condition found');
    this.test.assertSelectorHasText('tbody#condition-list tr:nth-child(3)', 'If cart products quantity is equal to 5','4) 3rd condition updated found');

    // Click on Delete button
    this.click('tbody#condition-list tr:nth-child(2) .condition-delete-btn');
});

casper.wait(2000, function() {
    this.echo("\nWaiting....");
});

casper.then(function(){
    this.test.assertSelectorHasText('tbody#condition-list tr:nth-child(1)', 'If cart total amount is superior to 40 EUR','5) 1st default condition found');
    this.test.assertSelectorDoesntHaveText('tbody#condition-list tr:nth-child(2)', 'If cart total amount is inferior to 400 EUR','5) 2nd default condition found');
    this.test.assertSelectorHasText('tbody#condition-list tr:nth-child(2)', 'If cart products quantity is equal to 5','5) 3rd condition updated found');
});

// Check if updated condition has been well saved
casper.thenOpen(thelia2_login_coupon_update_url, function() {
    this.test.assertHttpStatus(200);
    this.test.comment('Now on : ' + this.getCurrentUrl());
    this.capture('tests/functionnal/casperjs/screenshot/coupons/condition-deleted-refreshed.png');
    this.test.assertSelectorHasText('tbody#condition-list tr:nth-child(1)', 'If cart total amount is superior to 40 EUR','6) 1st default condition found');
    this.test.assertSelectorDoesntHaveText('tbody#condition-list tr:nth-child(2)', 'If cart total amount is inferior to 400 EUR','6) 2nd default condition found');
    this.test.assertSelectorHasText('tbody#condition-list tr:nth-child(2)', 'If cart products quantity is equal to 5','6) 3rd condition updated found');
});

// Test creating condition that won't be edited
casper.then(function(){
    // Create condition
    this.evaluate(function() {
        $('#category-condition').val('thelia.condition.match_for_total_amount').change();
        return true;
    });
    this.capture('tests/functionnal/casperjs/screenshot/coupons/condition-selected2.png');
});

casper.wait(2000, function() {
    this.echo("\nWaiting....");
});

// Test Condition creation
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
    this.capture('tests/functionnal/casperjs/screenshot/coupons/condition-saved-edited-before-click-save.png');
    this.click('#condition-save-btn');
});

casper.wait(2000, function() {
    this.echo("\nWaiting....");
});

casper.then(function(){
    this.test.assertSelectorHasText('tbody#condition-list tr:nth-child(1)', 'If cart total amount is superior to 40 EUR','7) 1st default condition found');
    this.test.assertSelectorDoesntHaveText('tbody#condition-list tr:nth-child(2)', 'If cart total amount is inferior to 400 EUR','7) 2nd default condition found');
    this.test.assertSelectorHasText('tbody#condition-list tr:nth-child(2)', 'If cart products quantity is equal to 5','7) 3rd condition updated found');
    this.test.assertSelectorHasText('tbody#condition-list tr:nth-child(3)', 'If cart total amount is inferior or equal to 401 GBP','7) 4rd condition created found');
});

// Check if created condition has been well saved
casper.thenOpen(thelia2_login_coupon_update_url, function() {
    this.test.assertHttpStatus(200);
    this.test.comment('Now on : ' + this.getCurrentUrl());
    this.capture('tests/functionnal/casperjs/screenshot/coupons/condition-added-refreshed.png');
    this.test.assertSelectorHasText('tbody#condition-list tr:nth-child(1)', 'If cart total amount is superior to 40 EUR','8) 1st default condition found');
    this.test.assertSelectorDoesntHaveText('tbody#condition-list tr:nth-child(2)', 'If cart total amount is inferior to 400 EUR','8) 2nd default condition found');
    this.test.assertSelectorHasText('tbody#condition-list tr:nth-child(2)', 'If cart products quantity is equal to 5','8) 3rd condition updated found');
    this.test.assertSelectorHasText('tbody#condition-list tr:nth-child(3)', 'If cart total amount is inferior or equal to 401 GBP','8) 4rd condition created found');
});

// Testing deleting all conditions
casper.then(function(){
// Click on Delete button
    this.click('tbody#condition-list tr:nth-child(1) .condition-delete-btn');
});
casper.wait(1000, function() {
    this.echo("\nWaiting....");
});
casper.then(function(){
// Click on Delete button
    this.click('tbody#condition-list tr:nth-child(1) .condition-delete-btn');
});
casper.wait(1000, function() {
    this.echo("\nWaiting....");
});
casper.then(function(){
// Click on Delete button
    this.click('tbody#condition-list tr:nth-child(1) .condition-delete-btn');
});
casper.wait(1000, function() {
    this.echo("\nWaiting....");
});
casper.then(function(){
    this.capture('tests/functionnal/casperjs/screenshot/coupons/condition-all-deleted.png');
    this.test.assertSelectorHasText('tbody#condition-list tr:nth-child(1)', 'No conditions','9) 1st default condition found');
    test.assertDoesntExist('tbody#condition-list tr:nth-child(2)');
});

// Check if created condition has been well saved
casper.thenOpen(thelia2_login_coupon_update_url, function() {
    this.test.assertHttpStatus(200);
    this.test.comment('Now on : ' + this.getCurrentUrl());
    this.capture('tests/functionnal/casperjs/screenshot/coupons/condition-all-deleted-refreshed.png');
    this.test.assertSelectorHasText('tbody#condition-list tr:nth-child(1)', 'No conditions','10) 1st default condition found');
    test.assertDoesntExist('tbody#condition-list tr:nth-child(2)');
});


// Test add no condition rule
casper.then(function(){
    this.evaluate(function() {
        $('#category-condition').val('thelia.condition.match_for_x_articles').change();
        return true;
    });
});

casper.wait(1000, function() {
    this.echo("\nWaiting....");
});

// Test Condition updating
casper.then(function(){
    this.evaluate(function() {
        $('#quantity-operator').val('>').change();
        return true;
    });
    this.sendKeys('input#quantity-value', '4');
    this.click('#condition-save-btn');
});

casper.wait(1000, function() {
    this.echo("\nWaiting....");
});

casper.then(function(){
    this.capture('tests/functionnal/casperjs/screenshot/coupons/condition-all-deleted.png');
    this.test.assertSelectorHasText('tbody#condition-list tr:nth-child(1)', 'If cart products quantity is superior to 4', '11) 1st default condition found');
    test.assertDoesntExist('tbody#condition-list tr:nth-child(2)');
});

// Check if created condition has been well saved
casper.thenOpen(thelia2_login_coupon_update_url, function() {
    this.test.assertHttpStatus(200);
    this.test.comment('Now on : ' + this.getCurrentUrl());
    this.capture('tests/functionnal/casperjs/screenshot/coupons/condition-all-deleted-refreshed.png');
    this.test.assertSelectorHasText('tbody#condition-list tr:nth-child(1)', 'If cart products quantity is superior to 4','12) 1st default condition found');
    test.assertDoesntExist('tbody#condition-list tr:nth-child(2)');
});

casper.then(function(){
    this.evaluate(function() {
        $('#category-condition').val('thelia.condition.match_for_everyone').change();
        return true;
    });
});

casper.wait(1000, function() {
    this.echo("\nWaiting....");
});

// Test Condition updating
casper.then(function(){
    this.click('#condition-save-btn');
});

casper.wait(1000, function() {
    this.echo("\nWaiting....");
});
casper.then(function(){
    this.capture('tests/functionnal/casperjs/screenshot/coupons/condition-all-deleted.png');
    this.test.assertSelectorHasText('tbody#condition-list tr:nth-child(1)', 'No condition','13) 1st default condition found');
    test.assertDoesntExist('tbody#condition-list tr:nth-child(2)');
});

// Check if created condition has been well saved
casper.thenOpen(thelia2_login_coupon_update_url, function() {
    this.test.assertHttpStatus(200);
    this.test.comment('Now on : ' + this.getCurrentUrl());
    this.capture('tests/functionnal/casperjs/screenshot/coupons/condition-all-deleted-refreshed.png');
    this.test.assertSelectorHasText('tbody#condition-list tr:nth-child(1)', 'No condition','14) 1st default condition found');
    test.assertDoesntExist('tbody#condition-list tr:nth-child(2)');
});

//RUN
casper.run(function() {
    this.test.done();
});