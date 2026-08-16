# DEH FAQ Autoresponder

Laravel + Filament app that will replace the current n8n-based DEH FAQ RAG
autoresponder.

## Goal

Replace the existing autoresponder workflow with a maintainable admin app that:

- ingests support/webinar FAQ emails,
- retrieves relevant knowledge from Postgres/pgvector,
- drafts German replies,
- lets a human review and edit replies in Filament,
- creates Gmail drafts after approval,
- saves approved answers for future reuse.

## Current Approach

The app is being built as a clean Laravel application with its own discrete
Postgres database. Existing FAQ data from the legacy Supabase database will be
migrated or re-ingested.

See `docs/planning.md` and `docs/implementation-plan.md` for the working plan.
See `docs/git-workflow.md` for the local `develop` / `main` branch workflow.
