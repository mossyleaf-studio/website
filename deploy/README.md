# mossyleaf.studio — running on a server

This folder is all a server needs: `compose.yaml` runs the app image (FrankenPHP, assets built in) and a PostgreSQL 18 database. No source checkout, no PHP or Node on the host.

## First install

```bash
mkdir -p "$DEPLOY_DIR" && cd "$DEPLOY_DIR"
# copy compose.yaml, compose.override.yaml, .env.dist and README.md here (`make deploy-files` from a dev machine does it)
cp .env.dist .env
chmod 600 .env
```

Fill `.env`:

| Variable | Value |
|---|---|
| `IMAGE` / `TAG` | Image to run; `make deploy` sets `TAG` to the commit it deploys |
| `DEFAULT_URI` | Public address, used for absolute links (`https://mossyleaf.studio`) |
| `PROXY_NETWORK` | External Docker network of the nginx reverse proxy (`docker network ls`) |
| `APP_SECRET` | `openssl rand -hex 16` |
| `POSTGRES_PASSWORD` | `openssl rand -hex 24` |
| `OIDC_CLIENT_SECRET` | Secret of the `mossyleaf-studio` provider in mossyleaf accounts (Authentik) |

Then `docker compose up -d`. The entrypoint waits for the database and runs the migrations: the site starts with the texts and links of the former static site.

## Who can sign in to `/admin`

Sign-in goes through mossyleaf accounts (`accounts.mossyleaf.studio`). An account needs the **`mossyleaf-studio`** group in Authentik: the application is bound to it, and the app checks the `groups` claim again (`OIDC_REQUIRED_GROUP`).

## Reverse proxy

- `compose.override.yaml` puts the app on the reverse proxy network (`PROXY_NETWORK`) with the alias `mossyleaf-studio`, and opens no host port.
- The `mossyleaf.studio` server block of the nginx configuration proxies to `http://mossyleaf-studio` and needs `client_max_body_size 22M` for image uploads, plus `X-Forwarded-Host`/`X-Forwarded-Port` like the mossydew block.
- Uploaded images live in the `app_share` volume (`var/share/artworks`), the texts and links in `database_data`.

## Updating

From a dev machine, with `DEPLOY_HOST` (`user@server`), `DEPLOY_DIR` and optionally `REMOTE_DOCKER` (default `docker`) set in a gitignored `.deploy.env` at the repository root, on a committed tree: `make ship` (full test suite, then build the image tagged with the commit and load it on the server over ssh, no registry), then `make deploy`.

## Backups

```bash
docker compose exec -T database pg_dump -U app app | gzip > backup-$(date +%F).sql.gz
docker run --rm -v mossyleaf-studio_app_share:/share -v "$PWD":/backup alpine tar czf /backup/artworks-$(date +%F).tgz -C /share artworks
```
