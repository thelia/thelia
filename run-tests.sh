#!/bin/bash
# --------------------------------------------------------------
# This script is started by Travis to perform all Thelia 2 tests
# --------------------------------------------------------------

# will exit with non-zero error code if any of the command fails
set -e

echo "backup DB"
mysqldump -h localhost -u $DB_USER thelia >../thelia.sql

echo "phpunit"
phpunit

echo "restore DB"
mysql -h localhost -u $DB_USER thelia <../thelia.sql

echo "deactivate modules only needed by phpunit tests"
php Thelia module:refresh
php Thelia module:deactivate HookTest

echo "Clearing cache"
php Thelia cache:clear --env=prod

echo "CasperJS"
cd ../casperjs
export DISPLAY=:99.0
./bin/casperjs test ../thelia/tests/functionnal/casperjs/exe/front/ --pre=../thelia/tests/functionnal/casperjs/conf/local.js --verbose --thelia2_base_url="http://localhost:8000/index.php/" --thelia2_screenshot_path="../thelia/tests/functionnal/casperjs/screenshot/"

echo "phpunit"
cd ../thelia
phpunit
