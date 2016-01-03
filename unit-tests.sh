#!/bin/bash
# Prepare environment for Thelia unit tests

echo -e "\n\033[01;34m[INFO] Clearing test cache\033[00m\n"
php Thelia cache:clear --env=test

echo -e "\n\033[01;34m[INFO] Downloading vendors\033[00m\n"
composer install --prefer-dist

echo -e "\n\033[01;34m[INFO] Refreshing Module list\033[00m\n"
php Thelia module:refresh

echo -e "\n\033[01;34m[INFO] Activating Hook Test Module\033[00m\n"
php Thelia module:activate HookTest

echo -e "\n\033[01;34m[INFO] Running unit tests\033[00m\n"
./bin/phpunit

echo -e "\n\033[01;34m[INFO] Desactivating Hook Test Module\033[00m\n"
php Thelia module:deactivate HookTest

echo -e "\n\033[01;34m[INFO] Removing hook test template\033[00m\n"
rm -rf templates/frontOffice/hooktest
