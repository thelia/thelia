casper.test.comment('== Front Homepage ==');

casper.test.begin('Front Homepage', 2, function suite(test) {

    casper.start(thelia2_base_url, function() {
        test.assertTitle("", "Thelia 2 homepage title is the one expected");
        test.assertExists('form#form-search', "main search form is found");
        this.capture(screenshot_dir + 'front/10_home.png');
    });

    casper.run(function() {
        test.done();
    });

});