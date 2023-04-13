#!/bin/sh

set -e

#COLORS
red=`tput setaf 1`
green=`tput setaf 2`
yellow=`tput setaf 3`
reset=`tput sgr0`

NEWLINE=$'\n'

TEMPLATE_NAME=modern
DB_FILE=./local/config/database.yml


echo  "Checking node is installed"
if command -v node > /dev/null 2>&1
then
    echo "${green}Node: OK${reset}"
else
    echo "${red}Node is not installed nor in your PATH${reset}"
    exit 1
fi

echo  "Checking yarn is installed"
if command -v yarn > /dev/null 2>&1
then
    echo "${green}Yarn: OK${reset}"
else
    echo "${red}Yarn is not installed or nor in your PATH${reset}"
    exit 1
fi

echo "Checking composer is installed"

if command -v composer > /dev/null 2>&1
  then
    echo "${green}Composer: OK${reset}"
  else
    echo "${red}Composer is not installed nor in your PATH${reset}"
    exit 1
fi


if test -f "$DB_FILE"; then
    read -p "$(echo "Would you like to erase the current database.yml file [y/n] ?")" erase
    if [ "$erase" != "${erase#[Yy]}" ] ;then
        echo  "Removing current database.yml"
        rm $DB_FILE
        rm -rf ./cache
    fi
fi

echo "Installing composer dependencies"
composer install

read -p "$(echo  "Enter a template folder name, (default: modern) it's recommended to change it : ")" TEMPLATE_NAME
TEMPLATE_NAME=${TEMPLATE_NAME:-modern}

if [ "$TEMPLATE_NAME" != "modern" ] ;then
  echo  "Copying template files to templates/frontOffice/$TEMPLATE_NAME"
  cp -r "templates/frontOffice/modern" "templates/frontOffice/$TEMPLATE_NAME";
fi

echo  "Creating session and media folder"
[ -d local/session ] || mkdir -p local/session
[ -d local/media ] || mkdir -p local/media

chmod -R +w local/session && chmod -R +w local/media


read -p "$(echo "Would you like to install Thelia [y/n] ?")" install
if [ "$install" != "${install#[Yy]}" ] ;then
    echo  "Installing Thelia"
    php Thelia thelia:install

    echo  "Activating modules"
    php Thelia module:refresh
    php Thelia module:activate OpenApi
    php Thelia module:activate ChoiceFilter
    php Thelia module:activate StoreSeo
    php Thelia module:activate SmartyRedirection
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
fi


echo  "Changing active template"
php Thelia template:set frontOffice modern # THELIA 2.5

read -p "$(echo "Would you like to create an administrator (y/n)?")" withAdmin
if [ "$withAdmin" != "${withAdmin#[Yy]}" ] ;then
  echo "Creating an admin account${NEWLINE} login:${yellow}thelia2${reset}${NEWLINE}password ${yellow}thelia2${reset}"
  php Thelia admin:create --login_name thelia2 --password thelia2 --last_name thelia2 --first_name thelia2 --email thelia2@example.com
fi


if test -f "$DB_FILE"; then
    read -p "$(echo "Would you like to install a sample database (y/n)?")" sample
    if [ "$sample" != "${sample#[Yy]}" ] ;then
      if test -f local/setup/import.php; then
        php local/setup/import.php
      elif test -f setup/import.php; then
        php setup/import.php
      else
        echo  "${red}Import script not found${reset}"
        exit
      fi
      echo  "${green}Sample data imported${reset}"
    fi
fi

rm -rf ./cache || exit

cd "templates/frontOffice/$TEMPLATE_NAME"

echo  "Installing dependencies with yarn"
yarn install || exit

echo  "Building template"
yarn build || exit


cd ../../..

echo  "${green}Everything is ok, you can now use your Thelia !${reset}"

exit 1
