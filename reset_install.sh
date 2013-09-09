#!/bin/bash
# @author Guillaume MOREL
# v0.2

echo -e "\033[47m\033[1;31m\n[WARN] This script will reset this Thelia2 install\n\033[0m"

echo -e "\n\e[01;34m[INFO] Clearing caches\e[00m\n"
php Thelia cache:clear

echo -e "\n\e[01;34m[INFO] Downloading vendors\e[00m\n"
composer install --prefer-dist

cd local/config/

echo -e "\n\e[01;34m[INFO] Building Models file\e[00m\n"
../../bin/propel build -v --output-dir=../../core/lib/

echo -e "\n\e[01;34m[INFO] Building SQL CREATE file\e[00m\n"
../../bin/propel sql:build -v --output-dir=../../install/

echo -e "\n\e[01;34m[INFO] Reloaded Thelia2 database\e[00m\n"
cd ../..
rm install/sqldb.map
php Thelia thelia:dev:reloadDB

echo -e "\n\e[01;34m[INFO] Installing fixtures\e[00m\n"
php install/faker.php

echo -e "\n\e[01;34m[INFO] Adding admin\e[00m\n"
php Thelia thelia:create-admin --login_name thelia2 --password thelia2 --last_name thelia2 --first_name thelia2

echo -e "\n\e[01;34m[INFO] Clearing caches\e[00m\n"
php Thelia cache:clear

echo -e "\n\e[00;32m[SUCCESS] Reset done\e[00m\n"