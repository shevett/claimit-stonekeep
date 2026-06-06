# ClaimIt Architecture

## Overview

ClaimIt is a PHP web application for community item-sharing. Users post items to give away or sell; others browse and claim them. It supports multiple communities, Google OAuth, AWS S3/CloudFront image hosting, and email via AWS SES.

## Request Lifecycle

All HTTP requests enter through `public/index.php` (~2100 lines). There is no routing framework — dispatch is manual:

1. Bootstrap: load config, start session, connect DB, authenticate user
2. AJAX branch: if `?ajax=1&page=home`, return JSON item feed and exit
3. Action branch: if `action=` param present, dispatch to item/claim/community mutation handlers
4. Page branch: if `page=` param present, collect data and render a template
5. Layout: wrap output in shared nav/footer HTML (also in `index.php`)

## Layer Map

```
public/index.php          — routing, AJAX dispatch, session bootstrap, HTML layout
includes/functions.php    — loader: requires all domain modules below
  includes/core.php       — DB connection, CSRF helpers, session utilities
  includes/auth.php       — session-based auth helpers
  includes/users.php      — user CRUD
  includes/items.php      — item CRUD, search
  includes/claims.php     — claim CRUD
  includes/communities.php— community CRUD, membership, admin management
  includes/images.php     — S3 upload, rotation, deletion, signed URLs
  includes/slack.php      — Slack webhook notifications
  includes/discord.php    — Discord webhook notifications
  includes/cache.php      — simple in-memory/file cache helpers
  includes/utilities.php  — misc helpers (escape, pagination, etc.)
src/AuthService.php       — Google OAuth flow (PKCE, token exchange)
src/AwsService.php        — S3/CloudFront/SES SDK wrapper
src/EmailService.php      — email dispatch via SES or SMTP
templates/                — plain PHP templates (item, items, community, admin, etc.)
db/migrations/            — Phinx migration files
config/                   — PHP config files (gitignored; copy from *.example.php)
```

## Database

MySQL/MariaDB via PDO prepared statements. Schema managed with Phinx.

Core tables:

| Table | Purpose |
|-------|---------|
| `users` | Google OAuth users |
| `items` | Posted items (title, description, images, status) |
| `claims` | User-to-item claim records |
| `communities` | Named communities with optional Slack/Discord webhooks |
| `users_communities` | Many-to-many: user membership in communities |
| `items_communities` | Many-to-many: item visibility in communities |
| `community_administrators` | Per-community admin grants |

All migrations must be idempotent — see `db/MIGRATION_GUIDELINES.md`.

## Key Pages / Routes

| `?page=` | Description |
|----------|-------------|
| `home` | Item feed (also AJAX endpoint for infinite scroll) |
| `items` | Full item list with search |
| `item&id=X` | Single item detail |
| `claim` | Post a new item |
| `user-listings&id=X` | Items posted by a user |
| `communities` | Community browser |
| `community&id=X` | Community detail |
| `admin` | Admin panel (gated by `ADMIN_USER_ID`) |
| `settings` | User settings |
| `auth&action=logout` | Logout |

## AJAX Actions (POST `action=`)

`add_claim`, `remove_claim`, `remove_claim_by_owner`, `delete_item`, `edit_item`, `rotate_image`, `mark_gone`, `relist_item`, `upload_additional_image`, `delete_image`, `republish_item`

Community actions (admin): `create`, `update`, `delete`, `test_slack`, `test_discord`, `add_admin`, `remove_admin`

## External Services

| Service | Purpose | Config file |
|---------|---------|-------------|
| Google OAuth | Authentication | `config/google-oauth.php` |
| AWS S3 | Image storage | `config/aws-credentials.php` |
| AWS CloudFront | Image CDN | `config/aws-credentials.php` |
| AWS SES / SMTP | Email notifications | `config/aws-credentials.php`, `config/smtp-config.php` |
| Slack webhooks | Community notifications | stored in `communities` table |
| Discord webhooks | Community notifications | stored in `communities` table |

## Configuration

Config is PHP files under `config/` (not `.env`). All are gitignored; copy from `*.example.php`.

- `config/config.php` — DB credentials, `DEVELOPMENT_MODE`, `CLOUDFRONT_DOMAIN`, `ADMIN_USER_ID`
- `config/aws-credentials.php` — AWS keys, S3 bucket, CloudFront distribution ID
- `config/google-oauth.php` — Google OAuth client ID and secret
- `config/smtp-config.php` — SMTP credentials (fallback to SES)

## Dev Setup

```bash
composer install
php -S localhost:8000 router.php   # router.php rewrites paths for the built-in server
vendor/bin/phinx migrate -e development
```

Static analysis: `vendor/bin/phpstan` | Code style: `vendor/bin/phpcs` (PSR-12)
