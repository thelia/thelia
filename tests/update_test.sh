#!/bin/bash

if [ $# -eq 5 ]
then
    password=""
elif [ $# -eq 6 ]; then
    password=$6
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
    php Thelia thelia:update
done

## Delete traces
if [ -z "$password" ]; then
    mysql_p_arg=""
else
    mysql_p_arg="-p$password"
fi

mysql -h$3 -u$5 $mysql_p_arg $4 -e "DROP DATABASE $4;"
cd ..
rm -rf $test_dir