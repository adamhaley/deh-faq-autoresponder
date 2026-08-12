# DEH FAQ Autoresponder

This project is the canonical home for the Laravel + Filament replacement for
the current DEH FAQ RAG autoresponder.

## Intent

- Build a pragmatic Laravel app for the DEH FAQ autoresponder.
- Use latest Laravel and latest Filament when scaffolding.
- Deploy through the existing Megyk Docker Compose stack for now.
- Use a discrete Postgres database for this app.
- Use direct Postgres/pgvector access from Laravel.
- Migrate or re-ingest the current FAQ knowledge from the legacy Supabase data.
- Keep n8n running during overlap, then cut over once the Laravel workflow is
  proven.
- Do not use Google Sheets in the Laravel implementation.

## Source Of Truth

- Planning lives in `docs/`.
- Existing n8n exports and reference material live in `references/`.
- The current behavioral reference is
  `/opt/homebrew/var/www/megyk-automations/workflows/Self-Learning_FAQs_RAG.json`.
- Current schema notes are in `docs/database-schema.md`.
- Current implementation plan is in `docs/implementation-plan.md`.

## Authentication

There are two independent Google integrations:

1. Filament user authentication
   - DEH team members sign in with their own Google accounts.
   - Use minimal identity scopes.
   - Use exact email allowlisting initially.
   - Use roles: `admin`, `reviewer`, `viewer`.
   - Keep a break-glass password admin login.

2. Gmail mailbox integration
   - Laravel uses separate Google/Gmail API credentials for the operational
     mailbox.
   - This account receives FAQ emails and creates or sends replies.
   - It is not tied to the logged-in Filament user.

## Development Preferences

- Use Laravel conventions and Eloquent models directly.
- Add only the tables required for the Filament workflow.
- Keep the first version human-reviewed: draft, edit, approve, then create a
  Gmail draft or send after approval.
- Use OpenAI directly through Laravel's first-party AI SDK.
- Use `text-embedding-3-small` and 1536-dimensional embeddings for continuity.
- Keep diffs small and document deployment friction as it is discovered.

## Supabase Ninja Mode

This client uses self-hosted Supabase. For schema inspection, debugging, and
migrations, prefer direct `psql` access over the Supabase CLI.

Store the direct Postgres connection string in the local, ignored `.env` file:

```bash
POSTGRES_PASSWORD=REPLACE_WITH_POSTGRES_PASSWORD
DATABASE_URL=postgresql://postgres:${POSTGRES_PASSWORD}@supabase.megyk.com:5433/postgres
```

Use it like this:

```bash
source .env && psql "$DATABASE_URL"
source .env && psql "$DATABASE_URL" -c "select now();"
source .env && psql "$DATABASE_URL" -f database/migrations/some_file.sql
```

Never print the full `DATABASE_URL` or commit real credentials. If a direct
connection fails, verify the current password from the production Supabase env
on the server before falling back to the Supabase SQL Editor.

## Laravel / Filament Guidelines

- Follow existing code conventions once the app is scaffolded.
- Use Artisan generators for Laravel files:
  - `php artisan make:model`
  - `php artisan make:migration`
  - `php artisan make:class`
  - `php artisan make:test --phpunit`
- Pass `--no-interaction` to Artisan commands where possible.
- Use explicit parameter and return types in PHP.
- Use constructor property promotion where it improves clarity.
- Always use curly braces for control structures.
- Prefer Laravel policies for authorization.
- Prefer queues for Gmail polling, embedding generation, retrieval, AI drafting,
  and send/draft actions.
- Write focused PHPUnit tests for workflow behavior and authorization.
- Run the smallest relevant test target after changes.
- Do not add dependencies without a clear reason.

## File Sync

Keep this file and `AGENTS.md` aligned. `CLAUDE.md` is for Claude-style tooling;
`AGENTS.md` is for Codex-style tooling.
