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
| `PROXY_NETWORK` | External Docker network of the reverse proxy (`web` on duprat.cloud) |
| `APP_SECRET` | `openssl rand -hex 16` |
| `POSTGRES_PASSWORD` | `openssl rand -hex 24` |
| `OIDC_CLIENT_SECRET` | Secret of the `mossyleaf-studio` provider in mossyleaf accounts (Authentik) |
| `MOSSYTRUNK_TOKEN` | `openssl rand -hex 32`, the same value as the token of the « Site web » service in mossytrunk. Empty or shorter than 32 characters: `/integrations/…` answers 404 and mossytrunk cannot push the catalogue |
| `MAILER_DSN` | SMTP of the mailbox that sends the site's emails, such as `smtp://user:password@smtp.example.com:587`. Empty: nothing is sent, so set it together with `STUDIO_EMAIL`, or the contact form accepts messages and drops them |
| `STUDIO_EMAIL` | Address of the studio: the contact form writes to it, from it, with the visitor as Reply-To. Empty: the shop offers no contact form and `/api/shop/…/contact` answers 404 |

Then `docker compose up -d`. The entrypoint waits for the database and runs the migrations: the site starts with the texts and links of the former static site.

## Who can sign in to `/admin`

Sign-in goes through mossyleaf accounts (`accounts.mossyleaf.studio`). An account needs the **`mossyleaf-studio`** group in Authentik: the application is bound to it, and the app checks the `groups` claim again (`OIDC_REQUIRED_GROUP`).

## Reverse proxy

- `compose.override.yaml` puts the app on the reverse proxy network (`PROXY_NETWORK`) with the alias `mossyleaf-studio`, and opens no host port.
- The `mossyleaf.studio` block of the Caddyfile (repo `duprat-cloud`, `proxy/conf/Caddyfile`) proxies to `mossyleaf-studio:80` with a 22 MB body limit for image uploads (`import app mossyleaf-studio:80 22MB`); `www.mossyleaf.studio` redirects to it. Caddy sends the `X-Forwarded-*` headers and handles the certificates.
- Uploaded images live in the `app_share` volume (`var/share/artworks`), the texts and links in `database_data`.

## Updating

From a dev machine, with `DEPLOY_HOST` (`user@server`), `DEPLOY_DIR` and optionally `REMOTE_DOCKER` (default `docker`) set in a gitignored `.deploy.env` at the repository root, push to `main`: GitHub Actions runs the full test suite, then publishes `docker.io/injust/mossyleaf-studio` tagged with the short commit and `latest`. Once it is green, `make deploy` points the server `.env` at that tag, pulls it and restarts. `make push` builds and pushes by hand (after `make qa` and `docker login`).

## Backups

```bash
docker compose exec -T database pg_dump -U app app | gzip > backup-$(date +%F).sql.gz
docker run --rm -v mossyleaf-studio_app_share:/share -v "$PWD":/backup alpine tar czf /backup/artworks-$(date +%F).tgz -C /share artworks
```
