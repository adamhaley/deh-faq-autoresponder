---
paths:
  - 'app/Jobs/**,app/Services/EmailQuestions/EmailThreadDraftComposerService.php,app/Services/Gmail/GmailClient.php'
---

# Gmail

## Gmail Output Is Drafts Only
The app creates and updates Gmail drafts only. Do not add direct Gmail send behavior unless the product scope explicitly changes; queue jobs should be unique/rate-limited so repeated UI actions do not pile up duplicate external API work.
