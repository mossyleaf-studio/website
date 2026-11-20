# mossyleaf.studio — project guide for Claude

Showcase site of the mossyleaf.studio illustration studio (sketchbook look: watercolor leaves, washi tape, paper cards) with an admin to edit every section. The public note at `/` is English only; the shop (in progress, previewed under `/beta/{fr,en}/`) is in **English and French**; admin UI in **English and French**. URLs, code, commits: **English**.

Architecture and conventions mirror `~/Sites/mossyleaf-studio/mossydew` (read its `CLAUDE.md` when in doubt).

## Code style

- **Never write comments** — no docblocks, no inline `//`/`#`, no `<!-- -->`, no `{# #}`. Only type-only PHPDoc the type system needs is allowed.
- **SOLID in every layer**: one use case per handler, one route per controller, one exception per file.

## Stack

- Symfony 8.1, PHP 8.5 (FrankenPHP, with GD for images), Doctrine ORM 3, PostgreSQL 18, ULIDs.
- Vue 3 + Vite through `pentatrion/vite-bundle`, three entries: `site` (public pages), `admin` (SPA with vue-router under `/admin`), `auth` (sign-in error page).
- Tests: PHPUnit 13 (unit + functional, DAMA rollback), Vitest, Playwright.

## Running things (always in Docker)

```bash
make up              # php + database + node (Vite :5175) + mock mossyleaf accounts (:8093) → http://localhost:8094 (admin on /admin)
make db              # create + migrate dev DB
make fixtures        # reset the content to the starting texts and links
make test / test-js / phpstan / deptrac / cs / cs-fix   # keep all at 0
make e2e             # Playwright against php-e2e (APP_ENV=test)
make shots           # screenshots of the site and the admin in e2e/shots/
make ci             # full suite from a fresh checkout, as GitHub Actions runs it
make deploy         # run the image CI published to Docker Hub for HEAD on DEPLOY_HOST (.deploy.env)
```

## Backend (Onion) — `src/`

Contexts: `Identity` (accounts), `Content` (`SiteText` single row, `Link`, `Artwork`) and `Catalogue` (`Product`, `ProductVariant`, `ProductPhoto`, `Category`, pushed by mossytrunk through `PUT /integrations/catalogue`, which first reads `GET /integrations/orders`: an empty list until the shop sells; the admin edits only the website side: French and English title and description, slug, visibility, order, photos, category labels; `CatalogueQueries` is the read side). `Ordering`, `Orderable` and the order/text exceptions live in `Domain\Shared`. Same layers and rules as MossyDew: Domain has no dependencies, Application holds use cases `<Context>/<UseCase>/{Command, Handler}`, views and ports (`ArtworkStorage`, `ImageResizer`, `Transaction`), Infrastructure the Doctrine repositories, `LocalArtworkStorage` (`var/share/artworks`) and `GdImageResizer` (WebP, 1600 px max), Presentation the controllers.

- **Draft and publication**: every admin edit changes the draft. `BetaController` (`/beta/`, full page) and `BetaNoteController` (`/beta/note/`, growing note) preview it for admins (noindex, no-store, draft banner). `PublishSite` freezes the draft `SiteView` as JSON in `PublishedSite` (single row, with the page shown and the media files it uses); `HomeController` (`/`) only serves that snapshot, or the draft growing note until the first publication. `MediaSweeper` deletes an image file only when neither the draft nor the published snapshot uses it: never delete media files directly. Product photos (full and thumb, stored through `ArtworkStorage`) are live and belong to no draft: the sweeper keeps every file listed by `ProductRepository::photoFiles()`.
- `templates/site.html.twig` writes the SEO head (title from `pageTitle`, canonical, Open Graph, Twitter card, JSON-LD) and `site/_prerender.html.twig` the content as plain HTML for crawlers; Vue replaces it on load, so both must show the same content. `robots.txt` and `sitemap.xml` are controllers. Uploaded images are served by `MediaController` (`/media/artworks/{ulid}.webp`, immutable cache).
- Admin API under `/api/admin/…` (texts per section, links, artworks, and `products`, `products/{id}`, `products/{id}/visibility`, `products/order`, `products/{id}/photos[/{photo}|/order]`, `categories`, `categories/{id}`, `categories/order`, one controller per route in `Presentation/Api/Catalogue`), `MapRequestPayload` DTOs, domain exceptions → 422 `problem+json`.
- **Shop preview**: `src/Presentation/Web/Shop/` holds one controller per page, routes `shop_*` at `/{_locale}/…` (`en|fr`), imported a second time under `/beta` as `beta_shop_*` with `preview: true` (editors only). `ShopDoor` decides if a shop URL is open (preview only for now); closed means 302 to `/`. `ShopUrls` is the only place that builds shop URLs: Twig and Vue get every link through the props. `ShopPage::render()` writes `{layout, data}` into `#site-content` and the `seo` variable for `templates/shop.html.twig` (prerender in `templates/shop/`). `ContentQueries::shop(bool $draft)` reads the draft, or the frozen `shop_content`. Interface labels live in `assets/vue/i18n/{en,fr}/site.json`, read by Vue (`vue/site/i18n.js`) and by Twig (`site_t`).
- **Shop pages**: `shop_listing` (`/{_locale}/shop`, optional `?category=<key>`, the key being the lower-case slug of the mossytrunk category name in the same locale whatever the page language, or the category ULID when that slug is empty or taken) and `shop_product` (`/{_locale}/shop/{slug}`); the home also carries the latest eight products and the category tiles. A product is *listed* when it is not retired and its price is above 0; the public also needs it visible, the preview adds hidden ones flagged `hidden`. An unknown category, an unlisted or retired product answers the shop 404. Title: website title in the page language, else the other language, else the mossytrunk `displayName`; category label: same chain ending on `name`. `CatalogueQueries::shop()/shopProduct()/related()` return `ShopProductView`; stock is computed only in `ShopVariantView::of` (`available` capped at 10, never the exact stock, and `state` `inStock`/`lastPieces` (3 or fewer)/`soldOut` that Twig and Vue both print). Prices travel as integer cents: Vue formats them with `assets/vue/site/money.js` (`Intl.NumberFormat`, EUR, page language) and the prerender with the Twig `money` filter, which gives the same output. The buy box emits `add` and shows its button only when a cart is provided (W8).
- Inline links in texts are written `[label](https://…)` and rendered by `assets/vue/site/richText.js` (never `v-html`).
- Text limits live on the entities (`SiteText::MAX_*`, `Link::MAX_*`, `Artwork::MAX_ALT`, `Product::MAX_*`, `Category::MAX_LABEL`) and are mirrored in `assets/vue/admin/limits.js`.
- Fonts: the admin types any Google Fonts family per `FontRole` (heading, body); empty means the bundled Gaegu / Kalam. `ChooseFont` asks `FontLibrary` (`GoogleFontLibrary`: CSS2 API for the faces, `fonts.google.com/metadata/fonts` cached a day for the names and autocomplete) for the closest weight in latin + latin-ext, and `LocalFontStorage` self-hosts it (`var/share/fonts`, `/media/fonts/{ulid}.css` + woff2, swept like the images): visitors never contact Google. `site.html.twig` links the stylesheets and sets `--font-display(-weight)` / `--font-body(-weight)` on `<html>`. Tests fake Google with `Tests\Support\FakeGoogleFonts`.

## Accounts & security

- Sign-in = mossyleaf accounts (Authentik at `accounts.mossyleaf.studio`, project `~/Sites/mossyleaf-studio/accounts`), OIDC code flow + PKCE, same classes as MossyDew. Only accounts whose `groups` claim contains `OIDC_REQUIRED_GROUP` (`mossyleaf-studio`) get in; dev leaves it empty.
- Dev and e2e use the mock OIDC server (`oidc` service): type any username plus claims such as `{"email": "editor@mossyleaf.test", "groups": ["mossyleaf-studio"]}`. PHPUnit uses `Tests\Support\FakeAccounts`.
- `/`, `/media`, `/robots.txt` and `/sitemap.xml` are public and start no session; `/admin`, `/beta` and `/api/` need `ROLE_USER`.

## Frontend — `assets/`

- `shop.js` is the Vite entry of the shop (`ShopApp.vue`, `styles/site/shop.css` scoped to `html[data-layout="shop"]`); `site.js` stays the note and `base.css` is never edited.
- `vue/site/`: public components fed by props from `#site-content`; tokens in `styles/site/`. The sketchbook design is deliberate: keep it, no drawn animals or illustrations (images come from the studio's own artworks).
- `vue/admin/`: a left rail (from 56rem) built from `GROUPS` in `AdminShell.vue`: Shop (Products: list `/admin/products`, editor `/admin/products/{id}`, `useShop.js`) above Site (Texts, Links, Images); `useContent.js` over `composables/useApi.js` (FormData uploads supported). Shared UI in `vue/components/ui/` (Reka UI, Lucide), MossyDew look and tokens in `styles/`.
- Never hardcode a user-visible admin string: `vue/i18n/<locale>/admin.json`.

## Git

One commit per feature, conventional messages. No co-author lines.
