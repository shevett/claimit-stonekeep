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
  includes/communities.php— community CRUD, membership, moderator management
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

All migrations must be idempotent — see `db/MIGRATION_GUIDELINES.md`.

### `users`
| Column | Type | Notes |
|--------|------|-------|
| `id` | string(255) PK | Google OAuth sub |
| `email` | string(255) unique | |
| `name` | string(255) | |
| `picture` | string(500) null | Google avatar URL |
| `verified_email` | boolean | |
| `locale` | string(10) null | |
| `is_admin` | boolean default false | Admin flag (supplemental to `ADMIN_USER_ID` config) |
| `display_name` | string(255) null | User-chosen display name |
| `zipcode` | string(10) null | |
| `show_gone_items` | boolean default false | |
| `email_notifications` | boolean default false | |
| `new_listing_notifications` | boolean default false | |
| `last_login` | datetime null | |
| `created_at` | datetime | |
| `updated_at` | datetime null | |

### `items`
| Column | Type | Notes |
|--------|------|-------|
| `id` | string(19) PK | Formerly `tracking_number`; format YmdHis or YmdHis-xxxx |
| `user_id` | string(255) | FK → users.id |
| `title` | string(255) | |
| `description` | text null | |
| `price` | decimal(10,2) default 0.00 | |
| `status` | string(50) default 'available' | Soft status field; see also `gone` |
| `image_file` | string(255) null | |
| `additional_images` | text null | JSON array of S3 keys |
| `image_width` | integer null | |
| `image_height` | integer null | |
| `contact_email` | string(255) null | |
| `user_name` | string(255) null | Denormalized at submission time |
| `user_email` | string(255) null | Denormalized at submission time |
| `submitted_at` | datetime null | |
| `submitted_timestamp` | integer null | Unix timestamp |
| `gone` | boolean default false | Primary "item is done" flag |
| `gone_at` | datetime null | |
| `gone_by` | string(255) null | user_id who marked gone |
| `relisted_at` | datetime null | |
| `relisted_by` | string(255) null | user_id who relisted |
| `created_at` | datetime | |
| `updated_at` | datetime | |

**Note:** Item availability is determined primarily by `gone`. The `status` column exists but its values are not consistently enforced in application logic — treat `gone=true` as the authoritative "done" state.

### `claims`
| Column | Type | Notes |
|--------|------|-------|
| `id` | integer PK auto | |
| `item_id` | string(19) | FK → items.id (formerly `item_tracking_number`) |
| `user_id` | string(255) | FK → users.id |
| `user_name` | string(255) null | Denormalized |
| `user_email` | string(255) null | Denormalized |
| `claimed_at` | datetime | |
| `status` | string(50) default 'active' | |
| `created_at` | datetime | |
| `updated_at` | datetime | |
Unique index on `(item_id, user_id, claimed_at)`.

### `communities`
| Column | Type | Notes |
|--------|------|-------|
| `id` | integer PK auto | |
| `short_name` | string(50) unique | URL slug |
| `full_name` | string(255) | |
| `description` | text null | |
| `owner_id` | string(255) | FK → users.id |
| `private` | boolean default false | Members-only visibility |
| `moderated` | boolean default false | New items require approval |
| `hide_new_items_by_default` | boolean default true | When moderated: new items start hidden |
| `slack_webhook_url` | text null | |
| `slack_enabled` | boolean default false | |
| `discord_webhook_url` | text null | |
| `discord_enabled` | boolean default false | |
| `created_at` | datetime | |
| `updated_at` | datetime null | |

### `users_communities`
| Column | Type | Notes |
|--------|------|-------|
| `id` | integer PK auto | |
| `user_id` | string(255) | FK → users.id |
| `community_id` | integer | FK → communities.id |
| `created_at` | timestamp | |
Unique index on `(user_id, community_id)`.

### `items_communities`
| Column | Type | Notes |
|--------|------|-------|
| `id` | integer PK auto | |
| `item_id` | string(19) | FK → items.id (formerly `item_tracking_number`) |
| `community_id` | integer | FK → communities.id |
| `status` | string(20) default 'online' | `online` or `hidden` (pending moderator approval) |
| `created_at` | timestamp | |
Unique index on `(item_id, community_id)`.

### `community_moderators`
| Column | Type | Notes |
|--------|------|-------|
| `id` | integer PK auto | |
| `user_id` | string(255) | FK → users.id |
| `community_id` | integer | FK → communities.id |
| `created_at` | timestamp | |
Unique index on `(user_id, community_id)`.
(Renamed from `community_administrators` in migration 20260617223836.)

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

Community actions (admin/owner/moderator): `create`, `update`, `delete`, `test_slack`, `test_discord`, `add_moderator`, `remove_moderator`

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

## Developer Entry Points

Authentication:
- src/AuthService.php
- includes/auth.php

Items:
- includes/items.php

Claims:
- includes/claims.php

Communities:
- includes/communities.php

Image handling:
- includes/images.php
- src/AwsService.php
