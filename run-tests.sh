#!/bin/bash
# --------------------------------------------------------------
# This script is started by Travis to perform all Thelia 2 tests
# --------------------------------------------------------------

# will exit with non-zero error code if any of the command fails
set -e

echo "backup DB"
mysqldump -h $DB_HOST -u $DB_USER thelia >../thelia.sql

echo "phpunit"
./bin/phpunit

echo "restore DB"
mysql -h $DB_HOST -u $DB_USER thelia <../thelia.sql

echo "deactivate modules only needed by phpunit tests"
php Thelia module:refresh
php Thelia module:deactivate HookTest

echo "Clearing cache"
php Thelia cache:clear --env=prod
rm -rf local/session/sess_*

echo "CasperJS"
echo "casperjs : $(which casperjs) $(casperjs --version)"
echo "phantomjs : ${PHANTOMJS_EXECUTABLE} $(${PHANTOMJS_EXECUTABLE} --version)"

export DISPLAY=:99.0

echo "Front tests"
casperjs test --local-to-remote-url-access=true --ignore-ssl-errors=true --ssl-protocol=any --pre=tests/functionnal/casperjs/conf/local.js --verbose tests/functionnal/casperjs/exe/front/ --thelia2_base_url="http://127.0.0.1:8000/index.php/" --thelia2_screenshot_path="tests/functionnal/casperjs/screenshot/" --thelia2_screenshot_disabled

echo "Back tests"
casperjs test --local-to-remote-url-access=true --ignore-ssl-errors=true --ssl-protocol=any --pre=tests/functionnal/casperjs/conf/local.js --verbose tests/functionnal/casperjs/exe/back/ --thelia2_base_url="http://127.0.0.1:8000/index_dev.php/" --thelia2_screenshot_path="tests/functionnal/casperjs/screenshot/" --thelia2_screenshot_disabled
