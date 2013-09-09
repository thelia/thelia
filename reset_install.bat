echo off
REM @author Guillaume MOREL
REM v0.1

echo [WARN] This script will reset this Thelia2 install

if exist local\config\database.yml (
    echo [INFO] Downloading vendors
    composer install --prefer-dist

    cd local\config\

    echo [INFO] Building Models file
    ..\..\bin\propel build -v --output-dir=../../core/lib/

    echo [INFO] Building SQL CREATE file
    ..\..\bin\propel sql:build -v --output-dir=../../install/


    echo [INFO] Reloaded Thelia2 database
    cd ..\..
    del install\sqldb.map
    php Thelia thelia:dev:reloadDB

    echo [INFO] Installing fixtures
    php install\faker.php

    echo [INFO] Adding admin
    php Thelia thelia:create-admin

    echo [SUCCESS] Reset done
)
) else (
    echo [FAILED] Please add your database informations in local\config\database.yml and start this script again.
)