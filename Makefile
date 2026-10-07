DC = docker compose
RUN = $(DC) run --rm
DEPLOY_HOST ?= user@server
DEPLOY_DIR ?= /path/to/mossyleaf-studio
REMOTE_DOCKER ?= docker

SHOT = $(RUN) playwright npx -y playwright@1.63.0 screenshot --wait-for-timeout=3500 --full-page

.PHONY: up down install build shots deploy

up: ## Start the Vite dev server on http://localhost:5175
	$(DC) up -d node

down:
	$(DC) down

install:
	$(RUN) --no-deps node npm install

build:
	$(RUN) --no-deps node npm run build

shots: ## Screenshot the page at phone and desktop widths into shots/
	mkdir -p shots
	$(SHOT) --viewport-size=390,844 http://node:5175/ shots/home-phone.png
	$(SHOT) --viewport-size=1440,900 http://node:5175/ shots/home-desktop.png
	$(SHOT) --viewport-size=390,844 http://node:5175/beta/ shots/beta-phone.png
	$(SHOT) --viewport-size=1440,900 http://node:5175/beta/ shots/beta-desktop.png

deploy: build ## Build and publish dist/ plus deploy/ config to the server, then (re)start the web container
	rsync -a deploy/compose.yaml deploy/nginx.conf $(DEPLOY_HOST):$(DEPLOY_DIR)/
	rsync -a --delete dist/ $(DEPLOY_HOST):$(DEPLOY_DIR)/html/
	ssh $(DEPLOY_HOST) 'cd $(DEPLOY_DIR) && $(REMOTE_DOCKER) compose up -d && $(REMOTE_DOCKER) compose exec -T web nginx -s reload'

DEPLOY_HOST ?= user@server
DEPLOY_DIR ?= /path/to/mossyleaf-studio

.PHONY: deploy

deploy: build ## Build and copy dist/ to DEPLOY_HOST:DEPLOY_DIR/html (served by nginx)
	rsync -az --delete dist/ $(DEPLOY_HOST):$(DEPLOY_DIR)/html/
