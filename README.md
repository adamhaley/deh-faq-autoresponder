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

## Running Locally

One command starts everything:

```bash
composer dev
```

It runs `php artisan dev`, which starts these processes side by side and
restarts any that crash:

| Name     | Command                                 | What it does                                          |
| -------- | --------------------------------------- | ----------------------------------------------------- |
| server   | `php artisan serve`                     | App on http://localhost:8000 (Herd also serves `.test`) |
| queue    | `php artisan queue:listen --tries=1`    | Retrieval, AI drafting and Gmail draft jobs           |
| schedule | `php artisan schedule:work`             | Gmail sync, extraction, classification, draft compose |
| reverb   | `php artisan reverb:start`              | Live updates in Filament                              |
| logs     | `php artisan pail`                      | Tails the application log                             |
| vite     | `npm run dev`                           | Frontend assets with hot reload                       |

Laravel registers server, queue, logs and vite by default. Schedule and reverb
are added in `AppServiceProvider`. Run `php artisan dev --help` for display
modes (`--tabs`, `--stream`, `--inline`).

The scheduler polls every active Gmail mailbox each minute, so with a real
mailbox connected locally it reads and drafts in that inbox. Deactivate the
mailbox, or skip the scheduler, if you only want to work on the UI.

Production runs the same pieces as separate containers (`queue:work`,
`schedule:work`, `reverb:start`); see `docs/deployment.md`.

## Docs

See `docs/planning.md` and `docs/implementation-plan.md` for the working plan.
See `docs/git-workflow.md` for the local `develop` / `main` branch workflow.
