
var system = require('system');

// Thalie Base URL
var thelia2_base_url = '';

if (casper.cli.has('thelia2_base_url')){
    thelia2_base_url = casper.cli.get('thelia2_base_url');
    casper.test.comment('CasperJS will use Thelia on : '+ thelia2_base_url);
} else if (system.env.thelia2_base_url){
    thelia2_base_url = system.env.thelia2_base_url;
    casper.test.comment('CasperJS will use Thelia (ENV) on : '+ thelia2_base_url);
} else {
    thelia2_base_url = 'http://dev.thelia.net/index_dev.php/';
    casper.test.comment('CasperJS will use fallback URL for tests : '+ thelia2_base_url);
    casper.test.comment('If you want to use custom URL just set the environment variable `thelia2_base_url`');
}

// Administrator account
var administrator = {
    login: "thelia2",
    password: "thelia2"
};

// Default
var thelia2_store_name = "Thelia V2";

var screenshot_enabled = true;
if (casper.cli.has('thelia2_screenshot_disabled')) {
    screenshot_enabled = false;
}

// Screenshot Dir
var screenshot_dir = 'tests/functionnal/casperjs/screenshot/';
if (casper.cli.has('thelia2_screenshot_path')){
    screenshot_dir = casper.cli.get('thelia2_screenshot_path');
}
casper.test.comment('Screenshots will be saved under : '+ screenshot_dir);


// Default Viewport size
casper.options.viewportSize = {width: 1024, height: 768};
casper.test.comment('Viewport size: '+ casper.options.viewportSize.width + 'x' + casper.options.viewportSize.height);

// Default timeout in ms
var thelia_default_timeout = 15000;
// for the waitFor method
casper.options.waitTimeout = thelia_default_timeout;
casper.options.logLevel = "info";

casper.test.comment('Default timeout: '+ thelia_default_timeout + ' ms');

// Email created during front/20_register.js test
var thelia_customer = {
    "email": null,
    "password": "thelia"
};

casper.test.done();
