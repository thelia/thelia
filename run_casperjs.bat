@echo off
REM @author Guillaume MOREL / Franck Allimant

if NOT DEFINED thelia2_base_url (
    echo [ERROR] thelia2_base_url environment variable not found. Please set this variable to the base URL of your Thelia
    echo [ERROR] for example: set thelia2_base_url=http://localhost/thelia2/index_dev.php/
    echo [ERROR] Be sure to add a final / to this URL !
    goto :EOF
)

REM use noreset parameter to start the test without rebuilding the whole universe
if XX%1==XXnoreset (goto :casperjs)

echo [INFO] Clearing caches
php Thelia cache:clear

echo [INFO] Self-updating Composer
call composer self-update

echo [INFO] -Downloading vendors
call composer install --prefer-dist --optimize-autoloader

echo [WARN] Force dropping database. All data will be lost (use "run_casperjs noreset" to skip database reload)
pause

cd local\config

echo [INFO] Building Models file
call ..\..\bin\propel build -v --output-dir=..\..\core\lib --enable-identifier-quoting

echo [INFO] Building SQL CREATE file
call ..\..\bin\propel sql:build -v --output-dir=..\..\setup

echo [INFO] Reloading Thelia2 database
cd ..\..
del setup\sqldb.map
php Thelia thelia:dev:reloadDB

echo [INFO] Installing fixtures
php setup\faker.php

echo [INFO] Adding admin
php Thelia admin:create --login_name thelia2 --password thelia2 --last_name thelia2 --first_name thelia2

echo [INFO] Clearing caches
php Thelia cache:clear

echo [INFO] Activating Delivery Module
php Thelia module:activate Colissimo

echo [INFO] Activating Payment Module(s)
php Thelia module:activate Cheque

:casperjs

for %%X in (casperjs.bat) do (set FOUND=%%~$PATH:X)
if NOT defined FOUND (
    echo [ERROR] casperjs not found. Please add to your PATH the casperjs batchbin directory, and the phantomjs bin directory.
    goto :EOF
)

casperjs test .\tests\functionnal\casperjs\exe\front --pre=.\tests\functionnal\casperjs\conf\local.js --verbose
