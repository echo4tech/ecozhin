# ecozhin
EcoZîn is a digital marketplace designed to build a circular economy within cities. Powered by a smart database and Natural Language Processing (NLP), the application automatically predicts agricultural waste volumes and seamlessly connects farmers with industrial buyers.

## Status vs. project plan

| Phase | Scope | State |
|---|---|---|
| 1 Foundation | structure, DB layer, auth, roles, RTL layout, i18n (ku/ar/en) | done |
| 2 Master data | units, products, waste types, geography, settings, admin CRUD | done |
| 3 Marketplace | farms, supply listings, images, buyer demands | next |
| 4–9 | matching, offers/orders, logistics, voice/NLP, analytics, hardening | planned |

Full schema for all plan sections (≈36 tables) is in `database/schema.sql`; later phases only add code.

## Setup

```bash
cp .env.example .env            # set DB_* values
php database/migrate.php --seed # create tables + seed catalog data
php database/create-admin.php "Admin" +9647500000000 'a-strong-password'
php -S 127.0.0.1:8000 router.php
php tests/api_test.php          # API integration tests (needs the server above + a test admin, see file header)
```

Requires PHP 8.2+ with PDO MySQL and MySQL 8+/MariaDB 10.6+.

## API (so far)

`GET /api/auth/csrf` · `POST /api/auth/{register,login,logout}` · `GET /api/auth/me` ·
`GET /api/{units,products,waste-types,geo/governorates,geo/districts,settings/public}` ·
admin: `POST /api/waste-types`, `PUT /api/waste-types/{id}`, `POST /api/products`.
All non-GET requests need the `X-CSRF-Token` header; responses use `{success, message, data|errors}`.
