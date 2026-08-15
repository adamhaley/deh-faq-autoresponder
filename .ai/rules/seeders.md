---
paths:
  - 'app/Models/FaqEntry.php,app/Filament/Resources/FaqEntries/**,app/Policies/FaqEntryPolicy.php,database/seeders/FaqEntrySeeder.php'
---

# Seeders

## Canonical FAQ Entries Are Read-Only In App
`faq_entries` are ingested from the canonical source-of-truth document and should not be manually created, edited, or deleted in Filament. Admins may inspect them; overrides belong in `faq_approved_responses` through the approved-answer workflow or admin correction.
