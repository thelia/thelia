#!/bin/bash

echo -e "\e[1;37;46m Checking node version \e[0m"
if which node > /dev/null
  then
    node_version="$(node --version  | cut -c 2,3)";
    if [[ "$node_version" =~ ^(10|11|12|14|15)$ ]]
      then
        echo -e "\e[1;37;42m Node: OK (v$node_version) \e[0m"
      else
        echo -e "\e[1;37;41m Your Node.js version isn't supported by this project, you need one of this versions: v10, v11, v12, v13, v14, v15 \e[0m"
        exit 1
    fi
  else
    echo -e "\e[1;37;41m Node.js is not installed or not in your PATH \e[0m"
    exit 1
fi

echo -e "\e[1;37;46m Checking yarn is installed \e[0m"
if ! command -v yarn &> /dev/null
then
    echo -e "\e[1;37;41m Yarn is not installed or not in your PATH \e[0m"
    exit
else
    echo -e "\e[1;37;42m Yarn: OK \e[0m"
fi

echo -e "\e[1;37;46m Checking composer is installed \e[0m"

if which composer > /dev/null
  then
    echo -e "\e[1;37;42m Composer: OK \e[0m"
  else
    echo -e "\e[1;37;41m Composer is not installed or not in your PATH \e[0m"
    exit 1
fi


DB_FILE=./local/config/database.yml

if test -f "$DB_FILE"; then
    read -p "$(echo -e "\e[1;37;45m Would you like to erase the current database.yml file (y/n)? \e[0m")" erase
    if [ "$erase" != "${erase#[Yy]}" ] ;then
        echo -e "\e[1;37;46m Removing current database.yml \e[0m"
        rm $DB_FILE
        rm -rf ./cache
        echo -e "\e[1;37;42m database.yml removed \e[0m"
    fi
fi

read -p "$(echo -e "\e[1;37;45m Enter a template folder name, (default: modern) it's recommended to change it :  \e[0m")" TEMPLATE_NAME
TEMPLATE_NAME=${TEMPLATE_NAME:-modern}

if [ "$TEMPLATE_NAME" != "modern" ] ;then
  echo -e "\e[1;37;46m Copying template files to templates/frontOffice/$TEMPLATE_NAME \e[0m"
  cp -r "templates/frontOffice/modern" "templates/frontOffice/$TEMPLATE_NAME";
  echo -e "\e[1;37;42m File copied \e[0m"
fi

echo -e "\e[1;37;46m Creating session and media folder if not exist \e[0m"
[ -d local/session ] || mkdir -p local/session
[ -d local/media ] || mkdir -p local/media

chmod -R +w local/session && chmod -R +w local/media
echo -e "\e[1;37;42m Folder created \e[0m"

echo -e "\e[1;37;46m Installing dependencies by composer \e[0m"
composer install
echo -e "\e[1;37;32 Dependencies installed \e[0m"

echo -e "\e[1;37;46m Installing Thelia \e[0m"
php Thelia thelia:install
echo -e "\e[1;37;42m Thelia installed \e[0m"

echo -e "\e[1;37;46m Activating needed modules \e[0m"
php Thelia module:refresh
php Thelia module:activate OpenApi
php Thelia module:activate ChoiceFilter
php Thelia module:activate StoreSeo
php Thelia module:activate SmartyRedirection
php Thelia module:deactivate HookAdminHome
php Thelia module:deactivate HookAnalytics
php Thelia module:deactivate HookCart
php Thelia module:deactivate HookCustomer
php Thelia module:deactivate HookSearch
php Thelia module:deactivate HookLang
php Thelia module:deactivate HookCurrency
php Thelia module:deactivate HookNavigation
php Thelia module:deactivate HookProductsNew
php Thelia module:deactivate HookSocial
php Thelia module:deactivate HookNewsletter
php Thelia module:deactivate HookContact
php Thelia module:deactivate HookLinks
php Thelia module:deactivate HookProductsOffer
php Thelia module:refresh
echo -e "\e[1;37;42m Module activated \e[0m"

echo -e "\e[1;37;46m Changing active template \e[0m"

php Thelia template:front "$TEMPLATE_NAME"

echo -e "\e[1;37;42m Active template changed \e[0m"

echo -e "\e[1;37;46m Creating an administrator \e[0m"

php Thelia admin:create

echo -e "\e[1;37;42m Administrator created \e[0m"

TEMPLATE_NAME=modern

if test -f "$DB_FILE"; then
    read -p "$(echo -e "\e[1;37;45m Would you like to install a sample database (y/n)? \e[0m")" sample
    if [ "$sample" != "${sample#[Yy]}" ] ;then
      if test -f local/setup/import.php; then
        php local/setup/import.php
      elif test -f setup/import.php; then
        php setup/import.php
      else
        echo -e "\e[1;37;41m Import script not found \e[0m"
        exit
      fi
        echo -e "\e[1;37;42m Sample data imported \e[0m"
    fi
fi

rm -rf ./cache

read -p "$(echo -e "\e[1;37;45m What's your BROWSERSYNC_PROXY(eg: http://myvhost.test) :  \e[0m")" vhost

if [ -z $vhost ]
then
    echo "To set your BROWSERSYNC_PROXY, you have to create .env file at the root of your template : templates/frontOffice/$TEMPLATE_NAME/"
else
    cd "templates/frontOffice/$TEMPLATE_NAME" && touch .env && echo BROWSERSYNC_PROXY="$vhost" > .env && cd -
fi

cd "templates/frontOffice/$TEMPLATE_NAME" || exit

echo -e "\e[1;37;46m Installing dependencies by yarn \e[0m"
yarn install || exit
echo -e "\e[1;37;42m Dependencies installed \e[0m"
echo -e "\e[1;37;46m Building template \e[0m"
yarn build || exit
echo -e "\e[1;37;42m Template builded \e[0m"

cd ../../..

echo -e "\e[1;37;42m Everything is good, you can now use your Thelia !  \e[0m"

# INIT CONSTANTS
# ------------------------------

exit 1