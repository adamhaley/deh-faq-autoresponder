---
paths:
  - 'app/Filament/**'
---

# Filament

## Keep app.timezone at UTC; set display timezone via FilamentTimezone
Storage and the scheduler must stay on UTC (config('app.timezone')) — changing it would shift cron scheduling and stored timestamp semantics.

For admin-panel display, use the env-driven `config('app.display_timezone')` (APP_DISPLAY_TIMEZONE), applied via `FilamentTimezone::set(...)` in AppServiceProvider::boot(). This is Filament's documented pattern and affects all dateTime()/time() columns and entries panel-wide (date-only fields are unaffected, per Filament docs). Don't hardcode timezones per-column unless a specific field genuinely needs to diverge from the panel default.
