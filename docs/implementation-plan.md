# Implementation Plan

## Current Intent

Build `DEH FAQ Autoresponder` as a latest Laravel + latest Filament app managed
inside the existing Megyk Docker Compose deployment for now.

The first implementation should be pragmatic and boring:

- replace the operational parts of the n8n workflow,
- preserve the behavior that currently works,
- avoid Google Sheets entirely,
- keep human review/edit/approval as a core product feature,
- use direct Postgres + pgvector,
- use OpenAI directly through Laravel's first-party AI SDK.

## Deployment Target

For now, deploy as another app service in the existing Megyk Compose stack.

Known Compose home:

```text
/opt/megyk/n8n-docker-caddy
```

The app should eventually become a dedicated service in that Compose file, with
its own app directory, database connection, queue worker, scheduler, and Caddy
route.

## Database Direction

Use a discrete Postgres database for this Laravel app rather than sharing all app
tables with the current Supabase database.

This means the existing FAQ data needs to move into the new app database. Viable
migration paths:

1. SQL dump/import from the current Supabase tables.
2. Clean re-ingest and re-embed the existing FAQ source material.
3. Hybrid: dump/import text rows, then regenerate embeddings in Laravel.

Current legacy FAQ tables:

- `public.faqs`
- `public.faq_approved_responses`

Current embeddings:

- model: `text-embedding-3-small`
- dimensions: `1536`

Prefer a clean app-owned schema, but keep it close enough to the existing schema
that migration is straightforward.

## Authentication

The app has two separate Google integrations.

### Filament User Login

Purpose: authenticate DEH team members into the admin app.

- Use Google OAuth for login.
- Use exact email allowlisting at first.
- Keep access deny-by-default.
- Use Laravel policies for authorization.
- Keep roles simple:
  - `admin`
  - `reviewer`
  - `viewer`
- Provide a break-glass admin/password login.

### Gmail Mailbox Integration

Purpose: let Laravel receive FAQ emails and create or send replies.

- Separate from Filament user login.
- Uses Gmail API credentials/scopes for the operational mailbox.
- Runs via scheduled polling and queued jobs.
- Must handle multiple new emails per polling interval.

## Email Workflow MVP

1. Scheduler polls Gmail on an interval.
2. Import all new matching messages since the last checkpoint.
3. Store raw message metadata and body.
4. Parse one or more customer questions from each message.
5. Generate a 1536-dim embedding for each question.
6. Retrieve the top FAQ matches from pgvector.
7. Generate German draft answer segments with OpenAI.
8. Compile a final editable email draft in Laravel.
9. Show the draft in Filament for human review.
10. Reviewer edits and approves.
11. Laravel creates a Gmail draft or sends after approval.
12. Store the final approved answer for future learning.

Default send behavior should be conservative: create Gmail drafts first, unless
we explicitly decide to allow direct send after approval.

## Filament Screens

Initial resources/pages:

- Inbound emails
- Extracted questions
- Draft replies
- Review queue
- FAQ knowledge entries
- Approved responses
- App users / allowlist
- Processing runs or job logs
- Settings for Gmail/OpenAI/retrieval thresholds

First scaffold pass includes:

- Google OAuth login routes via Socialite at `/auth/google` and
  `/auth/google/callback`
- Filament password login retained as the break-glass path
- deny-by-default team access through `authorized_emails`
- simple Filament resources for allowlisted emails, FAQ entries, and approved
  responses
- app-owned FAQ tables that mirror the useful shape of the legacy Supabase
  tables while using a discrete Laravel database

## Likely App-Owned Tables

These names are provisional and should be finalized during scaffolding.

- `users`
- `authorized_emails`
- `gmail_mailboxes`
- `gmail_messages`
- `email_questions`
- `faq_entries`
- `faq_approved_responses`
- `draft_replies`
- `draft_reply_questions`
- `processing_runs`

## AI Integration

Use Laravel's first-party AI SDK.

The official Laravel AI SDK supports:

- OpenAI as a provider,
- text generation,
- embeddings,
- queued workloads,
- fakes/testing helpers,
- pgvector-friendly vector columns.

Use OpenAI directly for both:

- embeddings: `text-embedding-3-small`, 1536 dimensions
- reply generation: model to be selected during implementation

Note: Laravel Boost MCP was not exposed in the current Codex session, so the
initial scaffold used installed package introspection, Artisan, and the local
`vendor/laravel/ai` package sources.

## Self-Learning Strategy To Iterate

This needs design iteration. Do not overbuild it in the first pass.

Possible mechanisms:

1. Store approved final answers as review history only.
2. Store one approved response override per matched FAQ, similar to the current
   `faq_approved_responses` table.
3. Promote approved Q/A pairs into new FAQ knowledge entries and embed them.
4. Keep both canonical FAQ entries and approval examples, then retrieve both.

Initial recommendation:

- Keep approved drafts in Laravel workflow tables.
- Also support curated promotion into the knowledge base.
- Do not automatically pollute FAQ knowledge with every approved email until the
  review UI makes that intent explicit.

## Known Risks

- Polling Gmail must process batches, not assume one email per run.
- Gmail API auth must not be coupled to Filament login.
- The new discrete Postgres database means we need a deliberate migration plan
  for FAQ rows and embeddings.
- Direct send should stay gated until review flow and audit logging are solid.
