.DEFAULT_GOAL := help

include .env
include .env.local

# =========================== SHORTCUT ===========================

Y = yarn
TEMPLATE_NAME=${ACTIVE_FRONT_TEMPLATE}
FRONT_OFFICE = templates/frontOffice/$(TEMPLATE_NAME)

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
	@lighthouse http://${TEMPLATE_NAME}.openstudio-lab.com --view --preset=desktop --quiet
