# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Supporting documentation
Read:
- docs/architecture.md
- docs/current-status.md

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

## Testing

- No automated tests currently exist.
- If making non-trivial changes, suggest appropriate tests.
- Do not assume test coverage exists.

## Architecture

See docs/architecture.md for architecture details.

## Investigation Strategy

- Read only files relevant to the current task.
- Prefer targeted searches over broad repository exploration.
- Do not scan the entire repository unless necessary.
- Before opening many files, explain why they are needed.
- Avoid reading generated files, vendor code, or migration history unless directly relevant.

## Change Philosophy

- Prefer minimal changes.
- Preserve existing behavior unless explicitly requested.
- Do not perform opportunistic refactors.
- If a larger refactor is recommended, propose it separately before implementing it.

## Session Handoff

After significant work:
- Summarize changes made.
- List files modified.
- Suggest next steps.
- Update docs/current-status.md when appropriate.
