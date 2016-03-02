echo off
REM @author Guillaume MOREL
REM v0.1

echo [WARN] This script will reset this Thelia2 install, all data will be cleared.
pause

if exist local\config\database.yml (

    echo [INFO] Clearing caches
    php Thelia cache:clear

    echo [INFO] Self-updating Composer
    composer self-update

    echo [INFO] Downloading vendors
    composer install --prefer-dist --optimize-autoloader

    cd local\config\

    echo [INFO] Building Models file
    ..\..\bin\propel build -v --output-dir=../../core/lib/ --enable-identifier-quoting

    echo [INFO] Building SQL CREATE file
    ..\..\bin\propel sql:build -v --output-dir=..\..\setup

    echo [INFO] Reloading Thelia2 database
    cd ..\..
    del setup\sqldb.map
    php Thelia thelia:dev:reloadDB

    echo [INFO] Installing fixtures
    php setup\faker.php

    echo [INFO] Clearing caches
    php Thelia cache:clear

    echo [INFO] Adding admin
    php Thelia admin:create --login_name thelia2 --password thelia2 --last_name thelia2 --first_name thelia2 --email thelia2@example.com

    echo [INFO] Admin user thelia2 with password thelia2 and email admin@example.com successfully created.

    echo [INFO] Activating Delivery Module
    php Thelia module:activate Colissimo

    echo [INFO] Activating Payment Module
    php Thelia module:activate Cheque

    echo [SUCCESS] Reset done
)
) else (
    echo [FAILED] Please add your database informations in local\config\database.yml and start this script again.
)
