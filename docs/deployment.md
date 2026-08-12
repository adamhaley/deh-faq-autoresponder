# Deployment

Deployment plan is pending.

Known related infrastructure:

- server base directory: `/opt/megyk/`
- main Compose home: `/opt/megyk/n8n-docker-caddy`
- legacy Supabase Compose home: `/opt/megyk/supabase/docker`
- legacy Supabase public URL: `https://supabase.megyk.com`
- Laravel Postgres service details: verify from the deployment environment
- n8n public URL: `https://n8n.megyk.com`

The Laravel app will likely be deployed as another service in the main Compose
stack once scaffolded.

## Google Credentials

The app will need separate Google OAuth/client configuration for:

- Filament user login with Google identity scopes.
- Gmail API mailbox access for receiving messages and creating or sending
  replies.

These credentials should be stored and rotated independently.
