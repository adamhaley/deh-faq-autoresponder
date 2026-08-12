# Agent Instructions for DEH FAQ Autoresponder

This project is the canonical home for the Laravel + Filament replacement for
the current DEH FAQ RAG autoresponder.

`CLAUDE.md` mirrors this project context for Claude-style tooling. Keep both
files aligned when changing project-level agent instructions.

## Intent

- Build a pragmatic Laravel app on top of the existing Postgres/Supabase data.
- Avoid over-engineered adapters or migration layers unless the schema requires
  them.
- Keep n8n running during overlap, then cut over once the Laravel workflow is
  proven.
- Prefer direct Postgres/pgvector access from Laravel.
- Preserve compatibility with the existing ingested knowledge store.

## Current Planning State

- Planning lives in `docs/`.
- Existing n8n exports and reference material live in `references/`.
- Do not scaffold Laravel until the workflow and schema plan are stable enough.

## Development Preferences

- Use Laravel conventions and Eloquent models directly over existing tables
  where practical.
- Add only the new tables required for the Filament workflow.
- Keep the first version human-reviewed: draft, edit, approve, then send or
  create a Gmail draft.
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
