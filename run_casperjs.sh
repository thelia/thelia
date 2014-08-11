#!/bin/bash
# @author Guillaume MOREL

echo -e "\n\033[01;34m[INFO] Clearing caches\033[00m\n"
php Thelia cache:clear

echo -e "\n\033[01;34m[INFO] Self-updating Composer\033[00m\n"
composer self-update

echo -e "\n\033[01;34m[INFO] Downloading vendors\033[00m\n"
composer install --prefer-dist --optimize-autoloader

echo "Force dropping database. All data will be lost."

cd local/config/

echo -e "\n\033[01;34m[INFO] Building Models file\033[00m\n"
../../bin/propel build -v --output-dir=../../core/lib/ --enable-identifier-quoting

echo -e "\n\033[01;34m[INFO] Building SQL CREATE file\033[00m\n"
../../bin/propel sql:build -v --output-dir=../../setup/

echo -e "\n\033[01;34m[INFO] Reloading Thelia2 database\033[00m\n"
cd ../..
rm setup/sqldb.map
php Thelia thelia:dev:reloadDB

echo -e "\n\033[01;34m[INFO] Installing fixtures\033[00m\n"
php setup/faker.php

echo -e "\n\e[01;34m[INFO] Adding admin\e[00m\n"
php Thelia admin:create --login_name thelia2 --password thelia2 --last_name thelia2 --first_name thelia2

echo -e "\n\033[01;34m[INFO] Clearing caches\033[00m\n"
php Thelia cache:clear

echo -e "\n\033[01;34m[INFO] Activating Delivery Module(s)\033[00m\n"
php Thelia module:activate Colissimo

echo -e "\n\033[01;34m[INFO] Activating Payment Module(s)\033[00m\n"
php Thelia module:activate Cheque

casperjs test ./tests/functionnal/casperjs/exe --pre=./tests/functionnal/casperjs/conf/local.js --direct
