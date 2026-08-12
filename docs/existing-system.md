# Existing System

Known source material lives outside this project:

- `/opt/homebrew/var/www/megyk-automations/workflows/Self-Learning_FAQs_RAG.json`
- `/opt/homebrew/var/www/markusdan/webinar-faqs/Self-Learning FAQs RAG.json`
- `/opt/homebrew/var/www/n8n/workflows/Webinar_FAQ_Responses.json`
- `/opt/homebrew/var/www/megyk-automations/FAQ Responder Approvals - Sheet1.csv`

For the initial Laravel implementation,
`/opt/homebrew/var/www/megyk-automations/workflows/Self-Learning_FAQs_RAG.json`
is the behavioral source of truth. The Filament app should first reproduce the
useful parts of that workflow before improving or replacing them.

The current workflow appears to use:

- Gmail trigger
- question parsing
- embeddings
- Supabase/Postgres vector retrieval
- OpenAI reply generation
- Google Sheets approval logging
- optional Gmail reply sending

The Laravel replacement should preserve the useful data shape while moving the
operational review workflow into Filament.

## Current Retrieval Flow

The n8n workflow currently:

1. Parses email text into:
   - `threadId`
   - `replyTo`
   - `replyToName.fullname`
   - `questions[]`
2. Generates an embedding for each question with OpenAI
   `text-embedding-3-small`.
3. Calls Supabase/PostgREST RPC:

   ```text
   POST https://supabase.megyk.com/rest/v1/rpc/match_faqs_json
   ```

   with:

   ```json
   {
     "query_embedding": "...",
     "match_count": 5
   }
   ```

4. Generates a German email reply segment from the top FAQ matches.
5. Appends or updates the Google Sheet named `FAQ Responder Approvals`.

## Current Approval Sheet Columns

The approval sheet uses these operational columns:

- `date`
- `thread_id`
- `match_uuid`
- `match_score`
- `Reply To`
- `Reply To Name`
- `Question`
- `Agent Response`
- `Approved`
- `Sent`

These fields are a good starting point for the Laravel workflow tables.

## Current Reply Prompt Behavior

The current reply generation:

- answers in German unless the customer explicitly asks for English
- uses retrieved FAQ answers as source of truth
- prefers a human-approved response when available
- does not invent facts beyond retrieved FAQ context
- uses a warm, professional tone for potential investors
- returns only email body segments, without greeting or signature
- nudges toward curiosity and scheduling an appointment on the last answer
