# Dev stack: site on http://localhost:8094, admin on /admin, Vite on :5175, mock mossyleaf accounts on :8093, Mailpit on :8095.
# Shared targets live in common.mk (synced from ~/Sites/mossyleaf-studio/toolchain).
# DEPLOY_HOST, DEPLOY_DIR and REMOTE_DOCKER come from .deploy.env (git-ignored).

IMAGE ?= docker.io/injust/mossyleaf-studio
UP_SERVICES = php database node oidc mailpit
DEPLOY_FILES = deploy/compose.yaml deploy/compose.override.yaml deploy/.env.dist deploy/README.md
E2E_RESET = $(EXEC) php-e2e rm -rf var/share/e2e

include common.mk

.PHONY: shots

shots: ## Screenshot the public pages and the admin at phone and desktop widths into e2e/shots/
	SHOTS=1 $(MAKE) e2e-run PLAYWRIGHT_ARGS="shots.spec.js --project=desktop"
