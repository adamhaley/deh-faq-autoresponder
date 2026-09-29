---
paths:
  - 'app/Models/FaqEntry.php,app/Filament/Resources/FaqEntries/**,app/Policies/FaqEntryPolicy.php,database/seeders/FaqEntrySeeder.php'
---

# Seeders

## Canonical FAQ Entries: Seeded, Plus Admin-Authored
`faq_entries` starts from the canonical source-of-truth document via `FaqEntrySeeder`, but admins may also create, edit, and delete entries directly in Filament (`FaqEntryPolicy::create/update/delete` gated on `$user->isAdmin()`, per the seeder's own "deliberate, out-of-band admin task" comment and `docs/implementation-plan.md` "Self-Learning Strategy"). Operators (reviewer role) must never get this capability -- their only write path to the corpus stays the automatic `faq_approved_responses` override on answer approval. `FaqEntryResource`'s create/edit form regenerates `embedding` via `FaqEntryEmbeddingService` on every save, embedding the question text alone (matching what retrieval embeds), so admin-authored entries are retrievable exactly like seeded ones.
