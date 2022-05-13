#!/bin/sh
set -e

set -o allexport
eval $(cat '.env' | sed -e '/^#/d;/^\s*$/d' -e 's/\(\w*\)[ \t]*=[ \t]*\(.*\)/\1=\2/' -e "s/=['\"]\(.*\)['\"]/=\1/g" -e "s/'/'\\\''/g" -e "s/=\(.*\)/='\1'/g")
set +o allexport

[ -d local/session ] || mkdir -p local/session
[ -d local/media ] || mkdir -p local/media
chmod -R +w local/session && chmod -R +w local/media

composer install

DB_FILE=local/config/database.yml
if ! test -f "$DB_FILE"; then
    php Thelia thelia:install --db_host=mariadb --db_port=3306 --db_username=root --db_name="${MYSQL_DATABASE}" --db_password="${MYSQL_ROOT_PASSWORD}"
    php Thelia module:refresh
    php Thelia module:activate OpenApi
    php Thelia module:activate ChoiceFilter
    php Thelia module:activate StoreSeo
    php Thelia module:activate ShortCode
    php Thelia module:activate ShortCodeMeta
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

    php Thelia template:set frontOffice "${ACTIVE_FRONT_TEMPLATE}"
    php Thelia template:set backOffice "${ACTIVE_ADMIN_TEMPLATE}"
    php Thelia admin:create --login_name thelia2 --password thelia2 --last_name thelia2 --first_name thelia2 --email thelia2@example.com
fi

php Thelia module:refresh
