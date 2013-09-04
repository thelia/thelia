#!/bin/bash
# @author Guillaume MOREL
# v0.1

echo -e "\033[47m\033[1;31m\n[WARN] This script will reset this Thelia2 install\n\033[0m"

if [ ! -f local/config/database.yml ]; then
    echo "[FAILED] Please add your database informations in local/config/database.yml and start this script again."
else
    echo -e "\n\e[01;34m[INFO] Downloading vendors\e[00m\n"
    composer install

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
    php Thelia thelia:create-admin

    echo -e "\n\e[00;32m[SUCCESS] Reset done\e[00m\n"
fi
