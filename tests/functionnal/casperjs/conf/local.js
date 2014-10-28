
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

// Screenshot Dir
var screenshot_dir = 'tests/functionnal/casperjs/screenshot/';
if (casper.cli.has('thelia2_screenshot_path')){
    screenshot_dir = casper.cli.get('thelia2_screenshot_path');
}
casper.test.comment('Screenshots will be saved under : '+ screenshot_dir);


// Default Viewport size
casper.options.viewportSize = {width: 1024, height: 768};
casper.test.comment('Viewport size: '+ casper.options.viewportSize.width + 'x' + casper.options.viewportSize.height);

// Default time to wait in ms
var thelia_default_timeout = 15000;
casper.test.comment('Default timeout: '+ thelia_default_timeout + ' ms');

casper.test.done();
