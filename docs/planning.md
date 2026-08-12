# Planning

## Decisions

- Build a Laravel + Filament app.
- Connect Laravel directly to Postgres with pgvector.
- Use the current Supabase/Postgres knowledge tables as the starting point.
- Use `/opt/homebrew/var/www/megyk-automations/workflows/Self-Learning_FAQs_RAG.json`
  as the behavioral source of truth for the first implementation.
- Create Eloquent models over existing tables instead of building an adapter
  layer.
- Add only minimal app-owned workflow tables.
- Keep n8n live during overlap.
- Cut over once Laravel has feature parity for the approval workflow.
- Existing FAQ knowledge lives in `public.faqs`; approved response overrides
  live in `public.faq_approved_responses`.
- Existing FAQ embeddings are 1536-dimensional vectors.
- Existing retrieval can be reproduced with `public.match_faqs_json(...)`.

## MVP Workflow

1. Import or receive a Gmail message.
2. Store the inbound email.
3. Extract one or more questions.
4. Search the existing knowledge store with pgvector.
5. Generate a draft German reply.
6. Review and edit the draft in Filament.
7. Approve and send, or create a Gmail draft.
8. Store the final approved answer for future retrieval.

## Likely Filament Screens

- Inbox queue
- Draft reply review
- Knowledge entries
- Approved answers
- Prompt/settings
- Processing logs

## Open Questions

- Should approval create Gmail drafts first or send directly?
- Which Gmail labels/accounts define the inbound queue?
- Which approved answers should be written back into the existing knowledge
  tables?
- Should new approved answers update existing FAQ rows, approved response rows,
  or create new FAQ rows with embeddings?
