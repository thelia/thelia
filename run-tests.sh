#!/bin/bash
# --------------------------------------------------------------
# This script is started by Travis to perform all Thelia 2 tests
# --------------------------------------------------------------

# will exit with non-zero error code if any of the command fails
set -e

echo "phpunit"
phpunit

echo "Clearing cache"
php Thelia cache:clear --env=prod

echo "CasperJS"
cd ../casperjs
export DISPLAY=:99.0
pwd
ls -la
./bin/casperjs test ../thelia/tests/functionnal/casperjs/exe/front/ --pre=../thelia/tests/functionnal/casperjs/conf/local.js --verbose --thelia2_base_url="http://localhost:8000/index.php/" --thelia2_screenshot_path="../thelia/tests/functionnal/casperjs/screenshot/"
