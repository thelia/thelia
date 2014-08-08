#!/bin/bash

## Get mysql password
if [ $# -eq 5 ]
then
    password=""
    mysql_p_arg=""
elif [ $# -eq 6 ]; then
    password=$6
    mysql_p_arg="-p$password"
else
    echo "usage: $0 your_thelia_repo your_branch db_host db_name db_username [db_password]"
    exit 1
fi

test_dir="test_update"

## Declare thelia versions
versions[0]="2.0.1"
versions[1]="2.0.2"
versions[2]="2.0.3-beta"
versions[3]="$2"

## Install 2.0.0
git clone $1 $test_dir
cd $test_dir

git checkout 2.0.0
composer install --prefer-dist --dev
php Thelia thelia:install --db_host=$3 --db_username=$5 --db_password=$password --db_name=$4

## Then explore the table and try to do the updates
for VERSION in ${versions[@]}; do
    git checkout $VERSION
    composer install
    if [ "$VERSION" = "2.0.3-beta" ]; then
        mysql -h$3 -u$5 $mysql_p_arg $4 < setup/update/2.0.3-beta.sql
    else
        php Thelia thelia:update
    fi
done

## Delete traces
mysql -h$3 -u$5 $mysql_p_arg $4 -e "DROP DATABASE $4;"
cd ..
rm -rf $test_dir