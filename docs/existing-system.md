# Existing System

Known source material lives outside this project:

- `/opt/homebrew/var/www/megyk-automations/workflows/Self-Learning_FAQs_RAG.json`
- `/opt/homebrew/var/www/markusdan/webinar-faqs/Self-Learning FAQs RAG.json`
- `/opt/homebrew/var/www/n8n/workflows/Webinar_FAQ_Responses.json`
- `/opt/homebrew/var/www/megyk-automations/FAQ Responder Approvals - Sheet1.csv`

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
