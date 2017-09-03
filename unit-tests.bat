@echo off
REM Prepare environment for Thelia unit tests

echo [INFO] Clearing test cache
php Thelia cache:clear --env=test

echo [INFO] Downloading vendors
call composer install --prefer-dist

echo [INFO] Refreshing Module list
php Thelia module:refresh

echo [INFO] Activating Hook Test Module
php Thelia module:activate HookTest

call bin\phpunit %*

echo [INFO] Desactivating Hook Test Module
php Thelia module:deactivate HookTest

echo [INFO] Removing hook test template
rd templates\frontOffice\hooktest /s /q