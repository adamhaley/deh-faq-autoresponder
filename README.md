# DEH FAQ Autoresponder

Canonical project home for the Laravel + Filament app that will replace the
current n8n-based DEH FAQ RAG autoresponder.

## Goal

Replace the existing autoresponder workflow with a maintainable admin app that:

- ingests support/webinar FAQ emails,
- retrieves relevant knowledge from the existing Postgres/pgvector store,
- drafts German replies,
- lets a human review and edit replies in Filament,
- sends or creates Gmail drafts after approval,
- saves approved answers for future reuse.

## Current Approach

The app should build directly on top of the current Postgres/Supabase schema
instead of creating a parallel knowledge system. During the transition, n8n and
Laravel may both use the same underlying knowledge tables.

See `docs/planning.md` for the working plan.
