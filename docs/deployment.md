# Deployment

Deployment plan is pending.

Known related infrastructure:

- server base directory: `/opt/megyk/`
- main Compose home: `/opt/megyk/n8n-docker-caddy`
- Supabase Compose home: `/opt/megyk/supabase/docker`
- Supabase public URL: `https://supabase.megyk.com`
- n8n public URL: `https://n8n.megyk.com`

The Laravel app will likely be deployed as another service in the main Compose
stack once scaffolded.
