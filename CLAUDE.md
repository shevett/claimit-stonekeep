# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

ClaimIt is a PHP web application for community item-sharing — users post items to give away or sell, others browse and claim them. It supports multiple communities, Google OAuth authentication, image hosting via AWS S3/CloudFront, and email notifications via AWS SES.

## Commands

### Development Server
```bash
php -S localhost:8000        # Starts the built-in PHP dev server at localhost:8000
```

### Database Migrations
```bash
vendor/bin/phinx migrate -e development    # Run pending migrations
vendor/bin/phinx status -e development     # Check migration status
vendor/bin/phinx create MigrationName      # Create a new migration
```

### Static Analysis & Code Style
```bash
vendor/bin/phpstan                         # Type checking (configured in phpstan.neon)
vendor/bin/phpcs                           # PSR-12 code style (configured in phpcs.xml)
```

### Install Dependencies
```bash
composer install
```

There is no test suite yet (PHPUnit is available but no tests exist).

## Architecture

### Entry Point & Routing
All HTTP requests go through `public/index.php`. It handles routing via query parameters (e.g., `?page=item&id=5`), AJAX requests, OAuth callbacks, and full-page renders. There is no routing framework — the URL dispatch logic is manual, inside `public/index.php`.

### Layer Organization

| Layer | Location | Purpose |
|-------|----------|---------|
| Entry/routing | `public/index.php` | URL routing, AJAX dispatch, session/auth bootstrap |
| Service classes | `src/` | AuthService (Google OAuth), AwsService (S3/CloudFront/SES), EmailService |
| Function modules | `includes/` | Organized by domain: `core.php`, `auth.php`, `users.php`, `items.php`, `claims.php`, `images.php`, `communities.php`, `slack.php`, `utilities.php` |
| Templates | `templates/` | Plain PHP templates rendered at end of `index.php` dispatch |
| DB migrations | `db/migrations/` | Phinx migration files |

`includes/functions.php` is a loader that requires all the individual function files.

### Configuration
Config is PHP files under `config/`, not `.env`. The required files (gitignored, copy from `*.example.php`):
- `config/config.php` — DB credentials, `DEVELOPMENT_MODE`, `CLOUDFRONT_DOMAIN`, `ADMIN_USER_ID`
- `config/aws-credentials.php` — AWS keys, S3 bucket, CloudFront distribution ID
- `config/google-oauth.php` — Google OAuth client ID and secret

### Database
MySQL/MariaDB via PDO prepared statements. Schema managed with Phinx migrations. All migrations **must be idempotent** (safe to run multiple times) — see `db/MIGRATION_GUIDELINES.md` before writing new ones.

Core tables: `users`, `items`, `claims`, `communities`, `users_communities`, `items_communities`.

### Image Handling
Images are uploaded to AWS S3 and served via CloudFront CDN. The `AwsService` and `includes/images.php` handle upload, rotation, signed URLs, and deletion. Without AWS credentials configured, image upload features won't work locally.

### Community & Slack
Items and users can belong to communities. Each community can have a Slack webhook URL for notifications (`includes/slack.php`).

## Key Conventions

- Database access uses PDO with prepared statements throughout — never string-interpolated queries.
- CSRF protection is applied to state-changing forms via helpers in `includes/core.php`.
- `DEVELOPMENT_MODE` constant controls debug output and error reporting — keep `true` in local config.
- The `ADMIN_USER_ID` constant in `config/config.php` is the Google OAuth user ID of the admin account; it gates admin panel access.
- Phinx environment names: `development`, `production` (defined in `phinx.php`).
