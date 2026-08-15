---
paths:
  - 'app/Models/EmailQuestionAnswerDraft.php,app/Services/EmailQuestions/EmailThreadDraftComposerService.php,app/Jobs/ComposeEmailThreadDraft.php'
---

# Jobs

## Gmail thread drafts auto-compose from approved answers, always in sync
Once every `Valid`-classified `EmailQuestion` in a Gmail thread (grouped by `gmail_messages.thread_id`) has an `Approved` answer, `EmailQuestionAnswerDraft::syncApprovedSideEffects()` dispatches `ComposeEmailThreadDraft`, which assembles all approved answers + a generated German greeting (`EmailGreetingGenerator`) into the single editable `EmailTemplate` row and creates/updates a Gmail draft via `EmailThreadDraftComposerService`.

`syncApprovedSideEffects()` must be called after *any* save that could change `final_answer` or `status` on an approved draft — not just on the initial approval. It's a no-op unless `status === Approved`. This is what makes the approved answer the source of truth: editing final_answer after approval silently re-composes and updates the existing Gmail draft (idempotent, keyed on `email_thread_drafts.thread_id`), by design (confirmed 2026-08-14) — there is no separate "resend to Gmail" review step, and no way to feed edits made directly in Gmail's own draft editor back into the app.

Never send directly — always `drafts.create`/`drafts.update`, never Gmail's `send`.
