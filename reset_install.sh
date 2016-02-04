#!/bin/bash
# @author Guillaume MOREL
# v0.2

echo -e "\033[47m\033[1;31m\n[WARNING] This script will reset this Thelia2 install\nPress ENTER to continue or ^C to cancel\033[0m"

read test

echo -e "\n\033[01;34m[INFO] Clearing caches\033[00m\n"
php Thelia cache:clear

echo -e "\n\033[01;34m[INFO] Self-updating Composer\033[00m\n"
composer self-update

echo -e "\n\033[01;34m[INFO] Downloading vendors\033[00m\n"
composer install --prefer-dist --optimize-autoloader

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

echo -e "\n\033[01;34m[INFO] Adding admin\033[00m\n"
php Thelia admin:create --login_name thelia2 --password thelia2 --last_name thelia2 --first_name thelia2 --email thelia2@example.com

echo -e "\n\033[01;34m[INFO] Clearing caches\033[00m\n"
php Thelia cache:clear

echo -e "\n\033[01;34m[INFO] Activating Delivery Module(s)\033[00m\n"
php Thelia module:activate Colissimo

echo -e "\n\033[01;34m[INFO] Activating Payment Module(s)\033[00m\n"
php Thelia module:activate Cheque

echo -e "\n\033[00;32m[SUCCESS] Reset done\033[00m\n"
