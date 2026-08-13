---
paths:
  - 'app/Services/EmailQuestions/**'
---

# Email Questions

## Persist Local FAQ Retrieval Matches
RAG retrieval should query local Postgres `faq_entries.embedding` with pgvector and persist ranked results in `email_question_faq_matches`. The n8n/Supabase RPC is only a reference spec, not a runtime dependency.
