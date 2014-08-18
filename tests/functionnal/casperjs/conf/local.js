// LOCAL = ton pc

var system = require('system');

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

var screenshot_dir = 'tests/functionnal/casperjs/screenshot/';
if (casper.cli.has('thelia2_screenshot_path')){
    screenshot_dir = casper.cli.get('thelia2_screenshot_path');
}

casper.options.viewportSize = {width: 1024, height: 768};

var viewports = [
    {
        'name': 'samsung-galaxy_y-portrait',
        'viewport': {width: 240, height: 320}
    },
    {
        'name': 'samsung-galaxy_y-landscape',
        'viewport': {width: 320, height: 240}
    },
    {
        'name': 'iphone5-portrait',
        'viewport': {width: 320, height: 568}
    },
    {
        'name': 'iphone5-landscape',
        'viewport': {width: 568, height: 320}
    },
    {
        'name': 'htc-one-portrait',
        'viewport': {width: 360, height: 640}
    },
    {
        'name': 'htc-one-landscape',
        'viewport': {width: 640, height: 360}
    },
    {
        'name': 'nokia-lumia-920-portrait',
        'viewport': {width: 240, height: 320}
    },
    {
        'name': 'nokia-lumia-920-landscape',
        'viewport': {width: 320, height: 240}
    },
    {
        'name': 'google-nexus-7-portrait',
        'viewport': {width: 603, height: 966}
    },
    {
        'name': 'google-nexus-7-landscape',
        'viewport': {width: 966, height: 603}
    },
    {
        'name': 'ipad-portrait',
        'viewport': {width: 768, height: 1024}
    },
    {
        'name': 'ipad-landscape',
        'viewport': {width: 1024, height: 768}
    },
    {
        'name': 'desktop-standard-vga',
        'viewport': {width: 640, height: 480}
    },
    {
        'name': 'desktop-standard-svga',
        'viewport': {width: 800, height: 600}
    },
    {
        'name': 'desktop-standard-hd',
        'viewport': {width: 1280, height: 720}
    },
    {
        'name': 'desktop-standard-sxga',
        'viewport': {width: 1280, height: 1024}
    },
    {
        'name': 'desktop-standard-sxga-plus',
        'viewport': {width: 1400, height: 1050}
    },
    {
        'name': 'desktop-standard-uxga',
        'viewport': {width: 1600, height: 1200}
    },
    {
        'name': 'desktop-standard-wuxga',
        'viewport': {width: 1920, height: 1200}
    },

];

;

casper.test.done();
