# Deployment

Live at `https://ai.deutsches-edelsteinhaus.de`, deployed as four extra
services in the existing shared Megyk Compose stack on a DigitalOcean VPS.

Deliberately on `deutsches-edelsteinhaus.de`, not `megyk.com` — this is a
Deutsches Edelsteinhaus product, hosted on shared infrastructure the DEH team
doesn't otherwise touch.

## Infrastructure

- VPS: `64.225.71.187`, Ubuntu 24.04, Docker Compose v5.
- Shared Compose home: `/opt/megyk/n8n-docker-caddy` (owned by the `megyk`
  system user, not root). `docker-compose.yml` and `caddy_config/Caddyfile`
  are hand-maintained, not version controlled — back up before editing.
- This app's own repo is cloned separately at
  `/opt/megyk/deh-faq-autoresponder` (as the `megyk` user), independent of
  the shared Compose directory.
- DNS: `ai.deutsches-edelsteinhaus.de` is an `A` record to the VPS IP,
  managed in DigitalOcean's DNS panel. Caddy auto-provisions/renews TLS via
  Let's Encrypt for any domain that resolves there — no extra Caddy config
  needed beyond the site block below.

## Compose Services

Appended to the shared `docker-compose.yml`:

- **`deh-faq-db`** — `pgvector/pgvector:pg17`, isolated Postgres + pgvector,
  own named volume `deh_faq_db_data`, no host port exposed (reachable only
  from other containers on the Compose `default` network). Fully separate
  from the legacy `supabase-db` instance — no shared state, no shared
  lifecycle.
- **`deh-faq`** — the web app, built from this repo's `Dockerfile`
  (FrankenPHP, `frankenphp php-server`, no Octane). Tagged
  `deh-faq-autoresponder:latest`.
- **`deh-faq-queue`** — same image, `php artisan queue:work --tries=3
  --max-time=3600`, `stop_grace_period: 30s` (gives in-flight jobs room to
  finish before a deploy's `SIGTERM`/`SIGKILL`; the `database` queue driver
  and idempotent job handlers make an occasional hard-kill safe regardless).
- **`deh-faq-scheduler`** — same image, `php artisan schedule:work`. Drives
  `gmail:sync-mailboxes`, `email-questions:extract`,
  `email-questions:classify` every minute.

All four are `restart: unless-stopped`. `deh-faq`/`deh-faq-queue`/
`deh-faq-scheduler` share one built image and get recreated together on every
deploy (picking up new code automatically); `deh-faq-db` is never touched by
a deploy — its data survives by construction, not luck (see below).

Caddy site block (`caddy_config/Caddyfile`):

```
ai.deutsches-edelsteinhaus.de {
    reverse_proxy deh-faq:8080 {
        flush_interval -1
    }
}
```

## Environment

`/opt/megyk/deh-faq-autoresponder/.env` lives only on the server (gitignored,
never committed). Notable production values:

- `APP_DISPLAY_TIMEZONE=Europe/Berlin` — the business's timezone, not
  whichever developer is looking at the panel (see
  `.ai/rules/filament.md`).
- `DB_HOST=deh-faq-db`, `DB_DATABASE=deh_faq_autoresponder` — direct
  Postgres/pgvector, no Supabase dependency.
- `QUEUE_CONNECTION=database`, `CACHE_STORE=database`,
  `SESSION_DRIVER=database` — no Redis dependency.
- OpenAI key, and two separate Google OAuth client credential pairs (see
  "Google OAuth" below).

**Important operational gotcha:** `docker compose restart` does **not**
reload `env_file` contents — it restarts the existing container with
whatever environment it was created with. Changing `.env` requires `docker
compose up -d deh-faq deh-faq-queue deh-faq-scheduler` to actually recreate
the containers with the new values.

## Google OAuth

Two independent concerns on the same Google Cloud OAuth client, per
`docs/planning.md`:

- Filament login: `GOOGLE_CLIENT_ID`/`SECRET`, redirect
  `https://ai.deutsches-edelsteinhaus.de/auth/google/callback`.
- Gmail mailbox integration: `GMAIL_CLIENT_ID`/`SECRET`, redirect
  `https://ai.deutsches-edelsteinhaus.de/integrations/gmail/callback`.

Both redirect URIs must be registered on the OAuth client in Google Cloud
Console (Google Auth Platform → Clients). The client's publishing status
(Google Auth Platform → Zielgruppe/Audience) must be **In Production**, not
Testing — Testing-mode refresh tokens expire after 7 days and would force
re-authorizing the Gmail mailbox weekly.

Connecting the Gmail mailbox (via the `Gmail Mailboxes` resource's connect
action) must be done from a browser session already signed into the target
mailbox's Google account, since Google's consent screen acts on whichever
account is active in that browser tab.

## Database

Data loading is split by lifecycle:

1. **Migrations** run automatically on every deploy (`migrate --force`).
2. **FAQ corpus seeding is one-time/as-needed, run manually**:
   `docker compose run --rm deh-faq php artisan db:seed --class=FaqEntrySeeder`.
   It is idempotent (safe to re-run) and only needs re-running if its source
   content changes:
   - `FaqEntrySeeder` — the canonical FAQ corpus, from the committed fixture
     `database/seeders/data/faq_entries.json` (with existing embeddings, no
     OpenAI re-embedding cost).
   - `EmailTemplateSeeder` runs during deploy as a create-only safety net for
     new environments. It does not overwrite an existing `Default` template,
     so edits made through the `Email Templates` resource survive deploys.

Production data survives redeploys because `deh-faq-db` is excluded from the
deploy script's container recreation, and even if it weren't, Compose only
recreates a container when its own config changes — the data itself lives in
the `deh_faq_db_data` named volume, independent of container lifecycle, and
is only destroyed by an explicit `docker compose down -v` or `docker volume
rm` (neither appears anywhere in this workflow).

## CI/CD

`.github/workflows/deploy.yml` — on every push to `main`:

1. **`test`** job: spins up a `pgvector/pgvector:pg17` service container,
   installs dependencies, runs `vendor/bin/pint --test`, migrates a fresh
   test database, runs the full test suite. Hard gate — `deploy` only runs
   if this passes.
2. **`deploy`** job: SSHes into the VPS as `megyk` and runs the same
   sequence used to stand this up manually — `git pull --ff-only`, `docker
   build`, `docker image prune -f`, `migrate --force`, recreate
   `deh-faq`/`deh-faq-queue`/`deh-faq-scheduler`, then curl `/up` to verify
   before declaring success.

Deploy access uses a dedicated ed25519 keypair scoped to the `megyk` user
only (never root), authorized in `megyk`'s `~/.ssh/authorized_keys`. Stored
as GitHub repo secrets: `DEPLOY_SSH_KEY`, `DEPLOY_HOST`, `DEPLOY_USER`.

From here, `git push` to `main` is the entire deploy process — no manual
server steps for ordinary code changes.

## Break-Glass Admin

Created once, manually, via `docker compose exec deh-faq php artisan
tinker` (not automated — there's no seeder or Artisan command for this).
To rotate the password or create another break-glass admin:

```php
App\Models\User::query()->updateOrCreate(
    ['email' => 'someone@example.com'],
    [
        'name' => 'Someone',
        'password' => 'new-password', // hashed automatically via cast
        'role' => App\Enums\UserRole::Admin,
        'is_active' => true,
        'email_verified_at' => now(),
    ],
);
```

Allowlisted reviewer emails (Google OAuth login, not password) are managed
through the `Allowlist` resource once logged in as an admin.

## Disk Hygiene

The shared VPS accumulates Docker image/build-cache bloat across every app
on the box, not just this one — a `docker system prune -a -f` reclaimed
~61GB once (75GB+ of it was reclaimable images/build cache, not real data).
The deploy workflow's `docker image prune -f` after each build (dangling
images only, safe on a shared multi-app host) slows reaccumulation but isn't
a full substitute — worth an occasional manual `docker system df` check.
