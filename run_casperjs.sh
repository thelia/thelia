#!/bin/bash
# @author Guillaume MOREL

echo "Force dropping database. All data will be lost."

cd local/config/

echo -e "\n\e[01;34m[INFO] Building Models file\e[00m\n"
../../bin/propel build -v --output-dir=../../core/lib/

echo -e "\n\e[01;34m[INFO] Building SQL CREATE file\e[00m\n"
../../bin/propel sql:build -v --output-dir=../../setup/

echo -e "\n\e[01;34m[INFO] Reloaded Thelia2 database\e[00m\n"
cd ../..
rm install/sqldb.map
php Thelia thelia:dev:reloadDB

echo -e "\n\e[01;34m[INFO] Installing fixtures\e[00m\n"
php setup/faker.php

echo -e "\n\e[01;34m[INFO] Adding admin\e[00m\n"
php Thelia thelia:create-admin --login_name thelia2 --password thelia2 --last_name thelia2 --first_name thelia2

casperjs test ./tests/functionnal/casperjs/exe --pre=./tests/functionnal/casperjs/conf/local.js --direct
