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

casper.test.comment('Testing Image Management');

// Image list
// @todo implement
////CREATE
casper.start(thelia2_category_image_list_url, function() {
    this.test.assertHttpStatus(200);
    this.test.comment('Now on : ' + this.getCurrentUrl());
    this.capture('tests/functionnal/casperjs/screenshot/category/images/init.png');
    this.test.comment('CATEGORY : IMAGES  - CREATE');

    // Click on is unlimited button
    this.clickLabel('Images', 'a');

});

casper.wait(1000, function() {
    this.echo("\nWaiting....");
});

// Test Coupon creation if no input
casper.then(function(){
    this.capture('tests/functionnal/casperjs/screenshot/category/images/init-tab-image.png');
    this.test.assertExists('.existing-image tr:nth-child(1)', 'First image found');

    this.click('.existing-image tr:nth-child(1) .image-update-btn');

});

casper.wait(1000, function() {
    this.echo("\nWaiting....");

});

// Test Coupon creation if no input
casper.then(function(){
    this.test.assertHttpStatus(200);
    this.test.comment('Now on : ' + this.getCurrentUrl());
    this.capture('tests/functionnal/casperjs/screenshot/category/images/read-image.png');
});

// Image add 1
// @todo implement

// Image add 4
// @todo implement

// Image read
// @todo implement

// Image update
// @todo implement

// Image delete
// @todo implement

// Image links
// @todo implement

// Image i18n
// @todo implement

//RUN
casper.run(function() {
    this.test.done();
});