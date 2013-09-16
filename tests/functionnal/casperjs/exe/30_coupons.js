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

casper.test.comment('Testing coupons');

////LIST
// @todo implement

////CREATE
casper.start(thelia2_login_coupon_create_url, function() {
    this.test.assertHttpStatus(200);
    this.test.comment('Now on : ' + this.getCurrentUrl());
    this.capture('tests/functionnal/casperjs/screenshot/coupons/init.png');
    this.test.comment('COUPON  - CREATE EMPTY');

    // Click on is unlimited button
    this.click("form #is-unlimited");
    this.sendKeys('input#max-usage', '-2');

    // cleaning expiration date default value
    this.evaluate(function() {
        $("#expiration-date").val('').change();
        return true;
    });

    this.capture('tests/functionnal/casperjs/screenshot/coupons/creating-new-coupon.png');
    this.click("form .control-group .btn.btn-default.btn-primary");

});

casper.wait(1000, function() {
    this.echo("\nWaiting....");
});

// Test Coupon creation if no input
casper.then(function(){
    this.test.assertHttpStatus(200);
    this.capture('tests/functionnal/casperjs/screenshot/coupons/created-new-empty-coupon.png');
    this.test.assertExists('.has-error #code', 'Error on code input found');
    this.test.assertExists('.has-error #title', 'Error on title input found');

    this.test.assertExists('.has-error #expiration-date', 'Error on expiration date input found');
    this.test.assertExists('.has-error #max-usage', 'Error on max usage input found');
    this.test.assertExists('.has-error #description', 'Error on description input found');
    this.test.assertExists('.has-error #effect', 'Error on effect input found');
    this.test.assertExists('.has-error #amount', 'Error on amount input found');
    this.test.assertExists('.has-error #short-description', 'Error on short-description input found');
});

// Test Coupon creation if good input
casper.then(function(){

    this.sendKeys('input#code', 'XMAS10');
    this.sendKeys('input#title', 'christmas');
    this.click("form #is-enabled");
    this.click("form #is-available-on-special-offers");
    this.click("form #is-cumulative");
    this.click("form #is-removing-postage");

    this.evaluate(function() {
        $("#expiration-date").val('2013-11-14').change();
        return true;
    });

    // Click on is unlimited button
    this.click("form #is-unlimited");
    this.sendKeys('input#max-usage', '40');

    this.evaluate(function() {
        $('#effect').val('thelia.coupon.type.remove_x_amount').change();
        return true;
    });

    this.test.assertSelectorHasText(
        '#effectToolTip',
        this.evaluate(function () {
            return $("#effect option[value^='thelia.coupon.type.remove_x_amount']").attr('data-description');
        }),
        'Tooltip found'
    );
    this.sendKeys('input#amount', '42.12');
    this.sendKeys('#short-description', 'Mauris sed risus imperdiet, blandit arcu ac, tempus metus. Aliquam erat volutpat. Nullam dictum sed.');
    this.sendKeys('#description', 'Etiam sodales non nisi a condimentum. Morbi luctus mauris mattis sem ornare; ac blandit tortor porta! Sed vel viverra dolor. Nulla eget viverra eros. Donec rutrum felis ut quam blandit, eu massa nunc.');

    this.capture('tests/functionnal/casperjs/screenshot/coupons/coupon-created-ready-to-be-saved.png');
    this.click("#save-coupon-btn");
});

casper.wait(2000, function() {
    this.echo("\nWaiting....");
});

// Test Coupon creation if good input is well saved
casper.then(function(){
    this.test.assertHttpStatus(302);
    this.test.comment('Now on : ' + this.getCurrentUrl());
    this.capture('tests/functionnal/casperjs/screenshot/coupons/created-new-coupon.png');
    this.test.assertField('thelia_coupon_creation[code]', 'XMAS10', 'Code found');
    this.test.assertField('thelia_coupon_creation[title]', 'christmas', 'Title found');

    this.test.assert(this.evaluate(function () {
        return document.getElementById('is-enabled').checked;
    }), 'Checkbox is enabled checked');
    this.test.assert(this.evaluate(function () {
        return document.getElementById('is-available-on-special-offers').checked;
    }), 'Checkbox is available on special offers checked');
    this.test.assert(this.evaluate(function () {
        return document.getElementById('is-cumulative').checked;
    }), 'Checkbox is cumulative checked');
    this.test.assert(this.evaluate(function () {
        return document.getElementById('is-removing-postage').checked;
    }), 'Checkbox is cumulative checked');

    this.test.assertField('thelia_coupon_creation[expirationDate]', '2013-11-14', 'Expiration date found');
    this.test.assertField('thelia_coupon_creation[maxUsage]', '40', 'Max usage found');
    this.test.assert(this.evaluate(function () {
        return !document.getElementById('is-unlimited').checked;
    }), 'Checkbox is unlimited not checked');

    this.test.assert(
        this.evaluate(function () {
            return $("#effect").val();
        }),
        'thelia.coupon.type.remove_x_amount',
        'Effect found'
    );
    this.test.assertSelectorHasText(
        '#effectToolTip',
        this.evaluate(function () {
            return $("#effect option[value^='thelia.coupon.type.remove_x_amount']").attr('data-description');
        }),
        'Tooltip found'
    );
    this.test.assertField('thelia_coupon_creation[amount]', '42.12', 'Amount found');

    this.test.assertField('thelia_coupon_creation[shortDescription]', 'Mauris sed risus imperdiet, blandit arcu ac, tempus metus. Aliquam erat volutpat. Nullam dictum sed.', 'Short description found');
    this.test.assertField('thelia_coupon_creation[description]', 'Etiam sodales non nisi a condimentum. Morbi luctus mauris mattis sem ornare; ac blandit tortor porta! Sed vel viverra dolor. Nulla eget viverra eros. Donec rutrum felis ut quam blandit, eu massa nunc.', 'Description found');


});
////EDIT CHECK
// @todo implement

////DELETE
// @todo implement

//RUN
casper.run(function() {
    this.test.done();
});