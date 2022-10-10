.DEFAULT_GOAL := help

include .env
include .env.local

YAML := greenit.yaml

# =========================== SHORTCUT ===========================

Y = yarn
TEMPLATE_NAME=${ACTIVE_FRONT_TEMPLATE}
SITE_NAME=thelia
FRONT_OFFICE = templates/frontOffice/$(TEMPLATE_NAME)
OUTPUT_PATH_LIGHTHOUSE = ./web/cache/quality/lighthouse
OUTPUT_PATH_GREENIT = ./web/cache/quality/greenit

URLS = $(shell cat greenit.yaml | grep url | sed 's/url: //g')
NAME_URLS = $(shell cat greenit.yaml | grep name | sed 's/name: //g' | sed 's/-//g')
compteur=0

# =========================== MAIN COMMANDS ===========================

help: ## show the help.
	@fgrep -hE '[a-zA-Z0-9_\-\/\.]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-30s\033[0m %s\n", $$1, $$2}'

init: ## init thelia
	@./setup/modern-init.sh

install: ## install existing project
	@composer install
	@if [ ! -d local/media ]; then \
		mkdir local/media; \
	fi;
	@php Thelia t:i
	@make install-front
	@make build

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
	@if [ ! -x "$(command -v lighthouse)" ]; then \
		echo 'Error: lighthouse is not installed.' >&2; |
		exit 1; \
	fi;
	@if [ ! -d $(OUTPUT_PATH_LIGHTHOUSE) ]; then \
		mkdir $(OUTPUT_PATH_LIGHTHOUSE); \
	fi;
	@lighthouse "$(SITE_NAME).openstudio-lab.com" --view --preset=desktop --quiet --output-path="$(OUTPUT_PATH_LIGHTHOUSE)/index.html" & \
	lighthouse "$(SITE_NAME).openstudio-lab.com/contact" --view --preset=desktop --quiet --output-path="$(OUTPUT_PATH_LIGHTHOUSE)/contact.html" & \
	lighthouse "$(SITE_NAME).openstudio-lab.com/a-propos.html" --view --preset=desktop --quiet --output-path="$(OUTPUT_PATH_LIGHTHOUSE)/content.html" & \
	lighthouse "$(SITE_NAME).openstudio-lab.com/search?query=t" --view --preset=desktop --quiet --output-path="$(OUTPUT_PATH_LIGHTHOUSE)/search.html" & \
	lighthouse "$(SITE_NAME).openstudio-lab.com/chairs.html" --view --preset=desktop --quiet --output-path="$(OUTPUT_PATH_LIGHTHOUSE)/category.html" & \
	lighthouse "$(SITE_NAME).openstudio-lab.com/barbara-1.html" --view --preset=desktop --quiet --output-path="$(OUTPUT_PATH_LIGHTHOUSE)/product.html" & \
	lighthouse "$(SITE_NAME).openstudio-lab.com/account" --view --preset=desktop --quiet --output-path="$(OUTPUT_PATH_LIGHTHOUSE)/account.html" & \
	lighthouse "$(SITE_NAME).openstudio-lab.com/account/update" --view --preset=desktop --quiet --output-path="$(OUTPUT_PATH_LIGHTHOUSE)/account-update.html" & \
	lighthouse "$(SITE_NAME).openstudio-lab.com/account-orders" --view --preset=desktop --quiet --output-path="$(OUTPUT_PATH_LIGHTHOUSE)/account-orders.html" & \
	lighthouse "$(SITE_NAME).openstudio-lab.com/account-address" --view --preset=desktop --quiet --output-path="$(OUTPUT_PATH_LIGHTHOUSE)/account-address.html" & \
	lighthouse "$(SITE_NAME).openstudio-lab.com/address/update" --view --preset=desktop --quiet --output-path="$(OUTPUT_PATH_LIGHTHOUSE)/account-address-update.html"

greenit: ## review green it
	@if [ ! -x $(npm ls --link --global | grep greenit) ]; then \
		echo "Error: greenit is not installed." >&2; \
		echo "Please look up for https://github.com/cnumr/GreenIT-Analysis-cli#installation"; \
		exit 1; \
	fi;
	@if [ ! -d $(OUTPUT_PATH_GREENIT) ]; then \
		mkdir $(OUTPUT_PATH_GREENIT); \
	fi;
	@greenit analyse setup/greenit.yaml $(OUTPUT_PATH_GREENIT)/global.html --ci --format=html && open $(OUTPUT_PATH_GREENIT)/global.html
