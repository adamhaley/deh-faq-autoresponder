---
paths:
  - '**/*'
  - phpunit.xml
---

# General

## Boundary between legacy Supabase and Laravel Postgres
The Laravel app uses Postgres/pgvector directly. Supabase is legacy infrastructure and an external source for inspection, migration, or re-ingestion, not a runtime dependency or integration target for new Laravel app code. Use Supabase Ninja Mode only when talking to the legacy self-hosted Supabase environment, preferably through direct psql.

## Run tests against local Postgres
The test suite should use the local Postgres test database `deh_faq_autoresponder_test` instead of SQLite. Keep the app and tests aligned with Postgres/pgvector behavior so migrations and vector columns are exercised locally.
