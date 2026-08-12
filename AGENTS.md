# Agent Instructions for DEH FAQ Autoresponder

This project is the canonical home for the Laravel + Filament replacement for
the current DEH FAQ RAG autoresponder.

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
- Keep diffs small and document deployment friction as it is discovered.
