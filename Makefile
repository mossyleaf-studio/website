DC = docker compose
NO_TTY = $(if $(CI),-T)
EXEC = $(DC) exec $(NO_TTY)
RUN = $(DC) run $(NO_TTY) --rm
PHP = $(EXEC) php
CONSOLE = $(PHP) php bin/console
PLAYWRIGHT_ARGS ?=

IMAGE ?= mossyleaf-studio
TAG ?= $(shell git rev-parse --short=7 HEAD)
PLATFORM ?= linux/amd64
-include .deploy.env
DEPLOY_HOST ?=
DEPLOY_DIR ?=
REMOTE_DOCKER ?= docker
NEEDS_DEPLOY_TARGET = @test -n "$(DEPLOY_HOST)" -a -n "$(DEPLOY_DIR)" || { echo "Set DEPLOY_HOST and DEPLOY_DIR (e.g. in .deploy.env)"; exit 1; }
E2E_ASSETS_DIR ?= build-e2e
export E2E_ASSETS_DIR
BUILD = docker buildx build --platform $(PLATFORM) --target prod -t $(IMAGE):$(TAG) -t $(IMAGE):latest

.PHONY: up down build install assets assets-e2e db db-test fixtures migration test test-unit test-functional test-js deptrac phpstan cs cs-fix e2e e2e-run shots qa image ship deploy deploy-files

up: ## Start the stack (site on http://localhost:8094, admin on /admin, Vite on :5175, mock mossyleaf accounts on :8093)
	$(DC) up -d --wait php database node oidc

down:
	$(DC) down

build:
	$(DC) build

install:
	$(PHP) composer install
	$(RUN) --no-deps node npm install

assets:
	$(RUN) --no-deps node npm run build

assets-e2e:
	$(RUN) --no-deps -e ASSETS_DIR=$(E2E_ASSETS_DIR) node npm run build

db: ## Create and migrate the dev database
	$(CONSOLE) doctrine:database:create --if-not-exists
	$(CONSOLE) doctrine:migrations:migrate --no-interaction --allow-no-migration

fixtures: db ## Reset the dev database with demo content
	$(CONSOLE) doctrine:fixtures:load --no-interaction --purge-with-truncate

db-test: ## Create and migrate the test database
	$(CONSOLE) doctrine:database:create --if-not-exists --env=test
	$(CONSOLE) doctrine:migrations:migrate --no-interaction --allow-no-migration --env=test

migration: ## Generate a migration from mapping changes
	$(CONSOLE) doctrine:migrations:diff --no-interaction

test: db-test ## PHPUnit (unit + functional)
	$(PHP) php bin/phpunit

test-unit:
	$(PHP) php bin/phpunit --testsuite unit

test-functional: db-test
	$(PHP) php bin/phpunit --testsuite functional

test-js: ## Vitest (pure JS modules)
	$(RUN) --no-deps node npx vitest run

deptrac: ## Check onion layer dependencies
	$(PHP) vendor/bin/deptrac analyse --no-progress

cs: ## Coding standard check
	$(EXEC) -e PHP_CS_FIXER_IGNORE_ENV=1 php vendor/bin/php-cs-fixer fix --dry-run --diff

cs-fix:
	$(EXEC) -e PHP_CS_FIXER_IGNORE_ENV=1 php vendor/bin/php-cs-fixer fix

phpstan: ## Static analysis (level 10)
	$(PHP) vendor/bin/phpstan analyse --no-progress --memory-limit=1G

e2e: assets-e2e e2e-run ## Playwright against a dedicated APP_ENV=test container

e2e-run: ## Playwright against already built assets (E2E_ASSETS_DIR, default build-e2e)
	$(DC) --profile e2e up -d --wait php-e2e
	$(EXEC) php-e2e php bin/console cache:clear --env=test
	$(EXEC) php-e2e php bin/console doctrine:database:drop --force --if-exists --env=test
	$(EXEC) php-e2e php bin/console doctrine:database:create --env=test
	$(EXEC) php-e2e php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration --env=test
	$(EXEC) php-e2e php bin/console doctrine:fixtures:load --no-interaction --env=test
	$(EXEC) php-e2e rm -rf var/share/e2e
	$(DC) --profile e2e run $(NO_TTY) --rm playwright sh -c "npm ci --no-audit --no-fund && ./node_modules/.bin/playwright test $(PLAYWRIGHT_ARGS)"

shots: ## Screenshot the public pages and the admin at phone and desktop widths into e2e/shots/
	$(MAKE) e2e-run PLAYWRIGHT_ARGS="shots.spec.js --project=desktop"

qa: cs phpstan deptrac test test-js e2e

image: ## Build the production image locally (IMAGE, TAG, PLATFORM)
	$(BUILD) --load .

ship: qa ## Run the full suite, build the production image and load it on DEPLOY_HOST over ssh (no registry)
	@git diff --quiet HEAD || { echo "Commit your changes first: the image is tagged with the commit."; exit 1; }
	$(BUILD) --load .
	docker save $(IMAGE):$(TAG) | gzip | ssh $(DEPLOY_HOST) 'gunzip | $(REMOTE_DOCKER) load'

deploy-files: ## Copy deploy/ (compose files, env template, README) to DEPLOY_HOST:DEPLOY_DIR
	$(NEEDS_DEPLOY_TARGET)
	ssh $(DEPLOY_HOST) 'mkdir -p $(DEPLOY_DIR)'
	scp deploy/compose.yaml deploy/compose.override.yaml deploy/.env.dist deploy/README.md $(DEPLOY_HOST):$(DEPLOY_DIR)/

deploy: ## Run IMAGE:TAG (loaded with make ship) on DEPLOY_HOST: set TAG in its .env, restart
	$(NEEDS_DEPLOY_TARGET)
	@ssh $(DEPLOY_HOST) '$(REMOTE_DOCKER) image inspect $(IMAGE):$(TAG) >/dev/null 2>&1' || { echo "$(IMAGE):$(TAG) is not on $(DEPLOY_HOST) yet: run make ship first."; exit 1; }
	scp deploy/compose.yaml $(DEPLOY_HOST):$(DEPLOY_DIR)/
	ssh $(DEPLOY_HOST) 'set -e; cd $(DEPLOY_DIR); \
		sed -i "s|^IMAGE=.*|IMAGE=$(IMAGE)|; s|^TAG=.*|TAG=$(TAG)|" .env; \
		$(REMOTE_DOCKER) compose up -d --remove-orphans'
