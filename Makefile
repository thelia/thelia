.DEFAULT_GOAL := help

include .env
include .env.local

# =========================== SHORTCUT ===========================

Y = yarn
TEMPLATE_NAME=${ACTIVE_FRONT_TEMPLATE}
SITE_NAME=thelia
FRONT_OFFICE = templates/frontOffice/$(TEMPLATE_NAME)
OUTPUT_PATH_LIGHTHOUSE = ./web/cache/audit/lighthouse
OUTPUT_PATH_GREENIT = ./web/cache/audit/greenit
# TODO Récupérer le bon path du setup.
# SETUP_PATH=

# =========================== MAIN COMMANDS ===========================

help: ## show the help.
	@fgrep -hE '[a-zA-Z0-9_\-\/\.]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-30s\033[0m %s\n", $$1, $$2}'

install: ## install existing project
	@composer install
	@if [ ! -d local/media ]; then \
		mkdir local/media; \
	fi;
	@php Thelia t:i
	@make activate-module
	@make install-front
	@make build

activate-module:
	@php Thelia module:refresh
	@php Thelia module:activate OpenApi
	@php Thelia module:activate ChoiceFilter
	@php Thelia module:activate StoreSeo
	@php Thelia module:activate SmartyRedirection
	@php Thelia module:deactivate HookAnalytics
	@php Thelia module:deactivate HookCart
	@php Thelia module:deactivate HookCustomer
	@php Thelia module:deactivate HookSearch
	@php Thelia module:deactivate HookLang
	@php Thelia module:deactivate HookCurrency
	@php Thelia module:deactivate HookNavigation
	@php Thelia module:deactivate HookProductsNew
	@php Thelia module:deactivate HookSocial
	@php Thelia module:deactivate HookNewsletter
	@php Thelia module:deactivate HookContact
	@php Thelia module:deactivate HookLinks
	@php Thelia module:deactivate HookProductsOffer
	@php Thelia module:refresh

install-front: ## install front
	@cd $(FRONT_OFFICE) && $(Y)

build: ## build front
	@cd $(FRONT_OFFICE) && $(Y) build

dev: ## start front
	@cd $(FRONT_OFFICE) && $(Y) start

reset-asset: ## reset assets
	@cd $(FRONT_OFFICE) && rm -rf dist && $(Y) build

cache-clear: ## clear cache
	@rm -rf var/cache & rm -rf web/assets & rm -rf web/cache & rm -rf web/templates-assets & rm -rf web/modules-assets

lighthouse: ## review lighthouse
	# @if [ ! -x "$(command -v lighthouse)" ]; then\
	# 	echo 'Error: lighthouse is not installed.' >&2;\
	# 	exit 1;\
	# fi;
	@if [ ! -d $(OUTPUT_PATH_LIGHTHOUSE) ]; then \
		mkdir $(OUTPUT_PATH_LIGHTHOUSE); \
	fi;
	@lighthouse --config-path:setup/audit/lighthouse.yaml --output-path="$(OUTPUT_PATH_LIGHTHOUSE)/index.html"

greenit: ## review green it
	@if [ ! -x $(npm ls --link --global | grep greenit) ]; then \
		echo "Error: greenit is not installed." >&2; \
		echo "Please look up for https://github.com/cnumr/GreenIT-Analysis-cli#installation"; \
		exit 1; \
	fi;
	@if [ ! -d $(OUTPUT_PATH_GREENIT) ]; then \
		mkdir $(OUTPUT_PATH_GREENIT); \
	fi;
	@greenit analyse setup/audit/greenit.yaml $(OUTPUT_PATH_GREENIT)/global.html --ci --format=html && open $(OUTPUT_PATH_GREENIT)/global.html
