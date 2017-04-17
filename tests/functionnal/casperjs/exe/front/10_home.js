casper.test.comment('== Front Homepage ==');

casper.test.begin('Front Homepage', 2, function suite(test) {

    casper.start(thelia2_base_url, function() {
        test.assertTitle(thelia2_store_name, "This is the home page : " + this.getTitle());
        test.assertExists('form#form-search', "main search form is found");
        if (screenshot_enabled) {
            this.capture(screenshot_dir + 'front/10_home.png');
        }
    });

    casper.run(function() {
        test.done();
    });

});