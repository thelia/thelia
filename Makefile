SHELL := /bin/sh
.DEFAULT_GOAL := help

-include .env
-include .env.local

# =========================== SHORTCUT ===========================

# Aliases
Y = yarn

# Template dir
FRONT_OFFICE = templates/frontOffice/$(ACTIVE_FRONT_TEMPLATE)

# SETUP CONSTANT
SETUP_PATH = $(shell test -d ./setup && echo "./setup" || echo "./local/setup")
OUTPUT_PATH_GREENIT = ./web/cache/audit/greenit

# COLORS
RED=$'\x1b[31m
GREEN=$'\x1b[32m
YELLOW=$'\x1b[33m
reset=`tput sgr0`

# =========================== MAIN COMMANDS ===========================

help: ## show the help.
	@grep -hE '[a-zA-Z0-9_\-\/\.]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-30s\033[0m %s\n", $$1, $$2}'

install: ## install existing project
	@composer install
	@if [ ! -d local/media ]; then \
		mkdir local/media; \
	fi;
	@php Thelia t:i
	@make activate-module
	@if [ ! -f .env.local ]; then \
			touch .env.local;\
	fi;
	@if ! grep -q ACTIVE_FRONT_TEMPLATE .env.local; then \
    echo '\nACTIVE_FRONT_TEMPLATE=modern' >> .env.local; \
	fi;
	@if ! grep -q ACTIVE_ADMIN_TEMPLATE .env.local; then \
			echo '\nACTIVE_ADMIN_TEMPLATE=default' >> .env.local; \
	fi;
	@make install-front
	@make build
	@make cache-clear
	@make remove-encore-files

remove-encore-files:
	@rm webpack.config.js
	@rm -rf assets

activate-module:
	@php Thelia module:refresh
	@php Thelia module:activate TwigEngine
	@php Thelia module:activate OpenApi
	@php Thelia module:activate ProductLoopAttributeFilter
	@php Thelia module:activate ChoiceFilter
	@php Thelia module:activate StoreSeo
	@php Thelia module:activate SmartyRedirection
	@php Thelia module:activate ShortCode
	@php Thelia module:activate ShortCodeMeta
	@php Thelia module:activate TheliaLibrary
	@php Thelia module:activate TheliaBlocks
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
	@if [ -z $(ACTIVE_FRONT_TEMPLATE) ] || [ -z $(ACTIVE_ADMIN_TEMPLATE) ]; then\
		echo "${RED}You need to add ACTIVE_FRONT_TEMPLATE and ACTIVE_ADMIN_TEMPLATE variable into your .env.local${reset}"; \
		echo "${RED}Example:${reset}";\
		echo "${RED}ACTIVE_FRONT_TEMPLATE=default${reset}";\
		echo "${RED}ACTIVE_ADMIN_TEMPLATE=default${reset}";\
		echo "${RED}Once this is done, restart make install-front{reset}";\
		exit 1;\
	fi;
	@cd $(FRONT_OFFICE) && $(Y)
	@make cache-clear

import-demo-db: ## import demo table into your database
	@php $(SETUP_PATH)/import.php
	@php Thelia admin:create --login_name thelia --password thelia --last_name thelia --first_name thelia --email thelia@example.com
	@make cache-clear

build: ## build front
	@cd $(FRONT_OFFICE) && $(Y) build
	@make cache-clear

dev: ## start front
	@cd $(FRONT_OFFICE) && $(Y) start

reset-asset: ## reset assets
	@cd $(FRONT_OFFICE) && rm -rf dist && $(Y) build

cache-clear: ## clear cache
	@rm -rf var/cache & rm -rf web/assets & rm -rf web/cache & rm -rf web/templates-assets & rm -rf web/modules-assets

clear-image-cache: ## clear image cache
	@rm -rf web/cache/images

lighthouse: ## review lighthouse
	@if [ -z $(LHCI_DOMAIN) ] || [ -z $(LHCI_PRESET) ]; then\
		echo "${RED}You need to add LHCI env variable into your .env.local${reset}"; \
		echo "${RED}LHCI_DOMAIN=your-domain.test${reset}";\
		echo "${RED}LHCI_PRESET=desktop${reset}";\
		exit 1;\
	fi;
	@if [ ! -f $(SETUP_PATH)/audit/lighthouserc.yaml ]; then \
		echo "${YELLOW}lighthouserc.yaml doesn't exists so creating with env config${reset}"; \
		cp $(SETUP_PATH)/audit/lighthouserc.default.yaml $(SETUP_PATH)/audit/lighthouserc.yaml; \
		sed -i '' 's/__PRESET__/$(LHCI_PRESET)/g' $(SETUP_PATH)/audit/lighthouserc.yaml;\
		sed -i '' 's/__DOMAIN__/$(LHCI_DOMAIN)/g' $(SETUP_PATH)/audit/lighthouserc.yaml;\
	fi;
	@if [ ! -x $(command -v lhci) ]; then\
		npm install -g @lhci/cli;\
	fi;
	@lhci collect --config=$(SETUP_PATH)/audit/lighthouserc.yaml
	@open .lighthouseci/*.html

greenit: ## review green it
	@if [ ! -x $(npm ls --link --global | grep greenit) ]; then \
		echo "${RED}Error: greenit is not installed.${reset}" >&2; \
		echo "Please look up for https://github.com/cnumr/GreenIT-Analysis-cli#installation"; \
		exit 1; \
	fi;
	if [ ! -f $(SETUP_PATH)/audit/greenit.yaml ]; then \
		echo "${YELLOW}greenit.yaml doesn't exists so creating with env config${reset}"; \
		cp $(SETUP_PATH)/audit/greenit.default.yaml $(SETUP_PATH)/audit/greenit.yaml; \
		sed -i '' 's/__DOMAIN__/$(LHCI_DOMAIN)/g' $(SETUP_PATH)/audit/greenit.yaml;\
	fi;
	@if [ ! -d $(OUTPUT_PATH_GREENIT) ]; then \
		mkdir -p $(OUTPUT_PATH_GREENIT); \
	fi;
	@greenit analyse $(SETUP_PATH)/audit/greenit.yaml $(OUTPUT_PATH_GREENIT)/global.html --ci --format=html && open $(OUTPUT_PATH_GREENIT)/global.html

cypress: ## run cypress
	@npx cypress run --project ./tests


audit: build greenit lighthouse ## audit website
