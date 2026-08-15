---
paths:
  - 'database/seeders/EmailTemplateSeeder.php,.github/workflows/deploy.yml,docs/deployment.md'
---

# Workflows

## Email Template Seeder Is Create-Only
`EmailTemplateSeeder` may run during deploy as a safety net for new environments, but it must never overwrite an existing `Default` template. Production template edits are made through Filament and must survive deploys.
