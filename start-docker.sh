#!/bin/bash

set -o allexport
eval $(cat '.env' | sed -e '/^#/d;/^\s*$/d' -e 's/\(\w*\)[ \t]*=[ \t]*\(.*\)/\1=\2/' -e "s/=['\"]\(.*\)['\"]/=\1/g" -e "s/'/'\\\''/g" -e "s/=\(.*\)/='\1'/g")
set +o allexport

if [ ! -z "$TEMPLATE_NAME" ] && [ ! -d "templates/frontOffice/$TEMPLATE_NAME" ]; then
  cp -r "templates/frontOffice/modern" "templates/frontOffice/$TEMPLATE_NAME";
fi

docker-compose up -d --build

docker-compose exec php-fpm docker-init

if  [[ $1 = "-import" ]]; then
   docker-compose exec php-fpm php setup/import.php
fi

docker-compose exec php-fpm php Thelia c:c
docker-compose exec php-fpm php Thelia c:c --env=prod
docker-compose exec php-fpm php Thelia c:c --env=propel