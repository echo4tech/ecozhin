# ecozhin
EcoZîn is a digital marketplace designed to build a circular economy within cities. Powered by a smart database and Natural Language Processing (NLP), the application automatically predicts agricultural waste volumes and seamlessly connects farmers with industrial buyers.

## Status vs. project plan

| Phase | Scope | State |
|---|---|---|
| 1 Foundation | structure, DB layer, auth, roles, RTL layout, i18n (ku/ar/en) | done |
| 2 Master data | units, products, waste types, geography, settings, admin CRUD | done |
| 3 Marketplace | farms, supply listings, images, search/filters, buyer demands (API) | done |
| UI | mobile-first installable web app (PWA): app shell, bottom nav, 7-step add-supply wizard, market + filters, demands, farms, profile | done |
| 4 Matching | scoring rules, distance, match records, notifications | next |
| 5–9 | offers/orders, logistics, voice/NLP, analytics, hardening | planned |

Full schema for all plan sections (≈36 tables) is in `database/schema.sql`; later phases only add code.

## Setup

```bash
cp .env.example .env            # set DB_* values
php database/migrate.php --seed # create tables + seed catalog data
php database/create-admin.php "Admin" +9647500000000 'a-strong-password'
php -S 127.0.0.1:8000 router.php
php tests/api_test.php          # API integration tests (needs the server above + a test admin, see file header)
php tests/marketplace_test.php  # phase 3 tests (farms, supplies, search, images, demands)
PLAYWRIGHT_PATH=/path/to/playwright node tests/e2e_mobile.js   # phone-sized browser end-to-end test
```

Requires PHP 8.2+ with PDO MySQL and MySQL 8+/MariaDB 10.6+.

## API (so far)

`GET /api/auth/csrf` · `POST /api/auth/{register,login,logout}` · `GET /api/auth/me` ·
`GET /api/{units,products,waste-types,geo/governorates,geo/districts,settings/public}` ·
admin: `POST /api/waste-types`, `PUT /api/waste-types/{id}`, `POST /api/products`.
All non-GET requests need the `X-CSRF-Token` header; responses use `{success, message, data|errors}`.

Phase 3: `GET/POST /api/farms`, `PUT /api/farms/{id}` ·
`GET /api/supplies` (public search: `waste_type_id, product_id, quality, min_price, max_price, min_quantity, available_by, q, lat, lng, radius_km, sort=newest|price_asc|quantity_desc|nearest, page, per_page`) ·
`GET /api/supplies/mine`, `POST /api/supplies` (`publish:true` to publish at once), `GET|PUT|DELETE /api/supplies/{id}`, `POST /api/supplies/{id}/publish`, `POST /api/supplies/{id}/images` (multipart `image`) ·
`GET /api/demands`, `/demands/mine`, `POST /api/demands`, `GET|PUT|DELETE /api/demands/{id}`, `POST /api/demands/{id}/publish`.

## Mobile web app

Open `/` on a phone: sign in or register, then the app lives at `/dashboard/` (hash-routed single page: `#/market`, `#/add`, `#/my`, `#/demands`, `#/farms`, `#/me`).
It has no framework or CDN dependencies (plain CSS + JS), so it works offline-first as a PWA: `manifest.php` (localised, RTL), `sw.js` (precaches the shell; caches only public catalog data, never pages or personal API responses) and `offline.html`.
Design rules: 48px touch targets, bottom navigation, bottom sheets for filters/forms, GPS-assisted location, camera capture with client-side photo downscaling, wizard drafts kept in `localStorage`, dark mode, safe-area insets, RTL/LTR per language (ku/ar/en).
