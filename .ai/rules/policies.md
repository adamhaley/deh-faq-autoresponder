---
paths:
  - 'app/Models/EmailQuestionAnswerDraft.php,app/Models/FaqEntry.php,app/Models/FaqApprovedResponse.php,app/Policies/FaqEntryPolicy.php,app/Policies/FaqApprovedResponsePolicy.php'
---

# Policies

## Self-learning: final_answer auto-writes FAQ override, canonical FAQ stays admin-only
Two separate answer surfaces, not one:
- Operator-facing: `email_question_answer_drafts.generated_answer` / `final_answer`. Approving a draft (`EmailQuestionAnswerDraft::markReviewed()`) automatically upserts `faq_approved_responses` for the single best-ranked (`rank` 1) matched FAQ, keyed on `faq_entry_id`. This is the only write path — deliberately automatic, deliberately scoped to one match, no separate "promote" action, to stay simple to reason about (multi-FAQ fan-out was considered and rejected as needless complexity).
- Admin-only canonical: `faq_entries.answer` (question/answer/embedding) and `faq_approved_responses` are both gated by `FaqEntryPolicy`/`FaqApprovedResponsePolicy` (`$user->isAdmin()`). Operators/reviewers must never edit either directly — that's the retrieval corpus and its override layer.

Generation (`EmailQuestionAnswerDraftGenerationService::formatMatches()`) reads the override if present, else the FAQ's canonical `answer`. Retrieval (embeddings/pgvector) never changes from this — only future generation prompts see updated text.
