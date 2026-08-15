# Planning

## Decisions

- Build a Laravel + Filament app.
- App name: `DEH FAQ Autoresponder`.
- Manage deployment through the existing Megyk Docker Compose stack for now.
- Connect Laravel directly to Postgres with pgvector.
- Use a discrete Postgres database for the Laravel app, then migrate or re-ingest
  the current FAQ knowledge.
- Use `/opt/homebrew/var/www/megyk-automations/workflows/Self-Learning_FAQs_RAG.json`
  as the behavioral source of truth for the first implementation.
- Create Eloquent models over existing tables instead of building an adapter
  layer.
- Add only minimal app-owned workflow tables.
- Keep n8n live during overlap.
- Cut over once Laravel has feature parity for the approval workflow.
- Do not use Google Sheets in the Laravel implementation. Sheet data is legacy
  reference material only.
- Existing FAQ knowledge lives in `public.faqs`; approved response overrides
  live in `public.faq_approved_responses`.
- Existing FAQ embeddings are 1536-dimensional vectors.
- Existing retrieval can be reproduced with `public.match_faqs_json(...)`.
- Keep Google login for Filament users completely separate from the Gmail
  mailbox integration used to receive and send FAQ emails.
- Use exact email allowlisting for app access initially.
- Use roles `admin`, `reviewer`, and `viewer` initially.
- Use OpenAI directly through Laravel's first-party AI SDK.
- Preserve a break-glass password admin login.

## MVP Workflow

1. Import or receive a Gmail message.
2. Store the inbound email.
3. Extract one or more questions.
4. Search the existing knowledge store with pgvector.
5. Generate a draft German reply.
6. Review and edit the draft in Filament.
7. Approve and create a Gmail draft.
8. Store the final approved answer for future retrieval.

See `implementation-plan.md` for the fuller current plan.

## Authentication And Google Accounts

There are two independent Google/OAuth concerns:

1. Filament user authentication
   - Lets DEH team members sign into the Laravel app with their own Google
     accounts.
   - Uses minimal identity scopes only.
   - Determines who the user is and what app permissions they have.
   - Access is deny-by-default and restricted with Laravel authorization.

2. Gmail mailbox integration
   - Lets Laravel receive FAQ emails and create Gmail drafts from the chosen
     operational mailbox.
   - Uses Gmail API scopes and credentials for that mailbox/integration.
   - Is not tied to the currently logged-in Filament user.
   - Runs through queued jobs and app-level configuration.

Do not conflate these. A reviewer logging into Filament with Google is not the
same actor as the Gmail mailbox used by the autoresponder workflow.

## Filament Screens

Done — see `docs/implementation-plan.md` "Filament Screens" for the current
list and `app/Filament/Resources/` for the authoritative source.

## Decisions (Resolved Open Questions)

- Approval creates a Gmail draft; it does not send directly. Direct send stays
  out of scope for now.
- The inbound queue is defined by the single active mailbox's `INBOX` label.
  Multiple mailboxes/labels are not needed yet.
- `Webinar Responses` is the reviewer workflow. `Email Questions` is an
  admin-only diagnostic surface for low-level pipeline details like RAG
  retrieval, ranking, and manual troubleshooting.
- Approving a draft automatically writes its `final_answer` as the override
  for the best-matched FAQ (see `implementation-plan.md` "Self-Learning
  Strategy"). It never touches canonical `faq_entries`, which stays
  read-only in the app after one-time ingestion from the canonical source
  document.
