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
| `MOSSYTRUNK_TOKEN` | `openssl rand -hex 32`, the same value as the token of the « Site web » service in mossytrunk. Empty or shorter than 32 characters: `/integrations/…` answers 404 and mossytrunk can neither read the sales (`GET /integrations/orders`, paid orders without personal data) nor push the catalogue (`PUT /integrations/catalogue`, whose stock the site lowers by the orders paid since mossytrunk last read them) |
| `MAILER_DSN` | SMTP of the mailbox that sends the site's emails, such as `smtp://user:password@smtp.example.com:587`. Empty: nothing is sent, so set it together with `MAILER_FROM` and `STUDIO_EMAIL`, or the contact form accepts messages and drops them |
| `MAILER_FROM` | Sender of every mail of the site, an address of a domain the SMTP account has verified (`shop@mossyleaf.studio`), never the inbox of the studio. Empty: no mail leaves, the shop offers no contact form and order mails are skipped |
| `STUDIO_EMAIL` | Inbox of the studio: it receives the contact messages (the visitor as Reply-To) and the notice of each paid order, and it is the Reply-To of the mails to customers. Empty: same as an empty `MAILER_FROM` |
| `SUMUP_API_KEY` | Secret API key of the SumUp account (`sup_sk_…`), with online payments enabled by SumUp. Empty: the shop offers no checkout |
| `SUMUP_MERCHANT_CODE` | Merchant code of the same SumUp account (profile, `M…`). Empty: the shop offers no checkout |

Then `docker compose up -d`. The entrypoint waits for the database and runs the migrations: the site starts with the texts and links of the former static site.

## Who can sign in to `/admin`

Sign-in goes through mossyleaf accounts (`accounts.mossyleaf.studio`). An account needs the **`mossyleaf-studio`** group in Authentik: the application is bound to it, and the app checks the `groups` claim again (`OIDC_REQUIRED_GROUP`).

## Reverse proxy

- `compose.override.yaml` puts the app on the reverse proxy network (`PROXY_NETWORK`) with the alias `mossyleaf-studio`, and opens no host port.
- The `mossyleaf.studio` block of the Caddyfile (repo `duprat-cloud`, `proxy/conf/Caddyfile`) proxies to `mossyleaf-studio:80` with a 22 MB body limit for image uploads (`import app mossyleaf-studio:80 22MB`); `www.mossyleaf.studio` redirects to it. Caddy sends the `X-Forwarded-*` headers and handles the certificates.
- Uploaded images live in the `app_share` volume (`var/share/artworks`), the texts and links in `database_data`.

## Opening and closing the shop

A new install, and every deploy until the studio decides, runs with the shop **closed**: `/` serves the growing note, every shop address leads back to `/` and the public shop API answers 404. Nothing but a publication opens it: there is no flag.

To open it for the first time:

1. Server `.env`: `SUMUP_API_KEY`, `SUMUP_MERCHANT_CODE`, `MAILER_DSN` (a real SMTP, not `null://`), `MAILER_FROM`, `STUDIO_EMAIL` and `MOSSYTRUNK_TOKEN` (32 characters or more), then `docker compose up -d`. Without a real mail transport, the shop refuses to open: order mails would be dropped silently.
2. mossytrunk: add the « Site web » service with the same token, the « En ligne » channel, then « Publier le catalogue ».
3. Admin, Products: give each product its title, description and photos, and make the ones for sale visible. Settings: turn at least one shipping zone on with its price. Legal pages: write the terms of sale, the legal notice and the privacy policy (shipping and returns is advised). Texts: the studio name, introduction and shop notes in both languages. Conventions as needed.
4. Check the preview with « Preview the draft » (`/beta/`, editors only), run one test order through it, then cancel or delete it in Orders.
5. Open the admin « Publish » bar. Its list shows what blocks (« Publier » stays disabled and the server refuses it too) and what only deserves a look (photos missing, stock not following mossytrunk, test orders still holding stock). Fix the blockers, click « Publish » and confirm. `/` now leads visitors to `/fr/` or `/en/` by their browser language, and the sitemap lists the shop pages.

Later « Publish » clicks put the waiting texts, links, images and legal pages live; products, photos, conventions, shipping and stock are live without it.

To close it: « Show the growing note » (« Afficher la note »). `/` is the note again and the shop addresses lead to `/`; customers who already ordered can still open the page of their order. The note keeps the texts of the draft at that moment.

After a deploy, check with `curl -sI https://mossyleaf.studio/` that the answer is still what the studio expects (200 with the note while closed, 302 to `/fr/` or `/en/` while open).

## Updating

From a dev machine, with `DEPLOY_HOST` (`user@server`), `DEPLOY_DIR` and optionally `REMOTE_DOCKER` (default `docker`) set in a gitignored `.deploy.env` at the repository root, push to `main`: GitHub Actions runs the full test suite, then publishes `docker.io/injust/mossyleaf-studio` tagged with the short commit and `latest`. Once it is green, `make deploy` points the server `.env` at that tag, pulls it and restarts. `make push` builds and pushes by hand (after `make qa` and `docker login`).

## Backups

```bash
docker compose exec -T database pg_dump -U app app | gzip > backup-$(date +%F).sql.gz
docker run --rm -v mossyleaf-studio_app_share:/share -v "$PWD":/backup alpine tar czf /backup/artworks-$(date +%F).tgz -C /share artworks
```
