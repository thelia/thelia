@echo off
REM Prepare environment for Thelia unit tests

echo [INFO] Clearing test cache
php Thelia cache:clear --env=test

echo [INFO] Downloading vendors
call composer install --prefer-dist

echo [INFO] Activating Hook Test Module
php Thelia module:activate HookTest

call phpunit %*

echo [INFO] Desactivating Hook Test Module
php Thelia module:activate HookTest

echo [INFO] Removing hook test template
del /f /s /q templates\frontOffice\hooktest