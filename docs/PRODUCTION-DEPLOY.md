# Pupilovo: preparation for a public server

This is a **deployment scaffold, not a live-store readiness certification**. The local development stack is unchanged.

## On a future VPS

1. Install Docker Engine and Docker Compose; configure SSH, firewall and DNS A/AAAA records to the server. Open ports 80/443.
2. Clone the repository, copy `.env.production.example` to `.env.production` and set the real domain, admin TLS email and independent strong database passwords. Never commit secrets.
3. Validate: `docker compose --env-file .env.production -f docker-compose.production.yml config --quiet`
4. Start: `docker compose --env-file .env.production -f docker-compose.production.yml up -d --build`
5. Caddy requests HTTPS certificates when domain DNS and ports are working.
6. WordPress and WooCommerce need initial installation or a **tested migration** of the existing database, media and installed WooCommerce plugins. Compose does not transfer local store content.
7. Configure real transactional SMTP (SPF/DKIM/DMARC) and approved payment provider. Never enable Mailpit for public customer traffic.

## Architecture

Caddy HTTPS reverse proxy -> compiled React static frontend (Nginx) and WordPress API/admin. MySQL and WordPress are not exposed directly. Production runs without Vite dev server, source bind-mount to frontend or Docker socket.

## Mandatory prelaunch checks

- Verify all REST, auth, WooCommerce Store API, checkout and media endpoints through the same HTTPS domain; adjust proxy routes if needed.
- Set up production SMTP, payment gateway, shipping, tax, returns, legal pages and GDPR handling.
- Harden admin accounts, update WordPress/plugins, protect secrets, enable backups **off-server** and perform restore drills.
- Test browser-based checkout, emails, cancellations/refunds, supplier integration and stock handling.
- Configure logging, uptime and disk alerts.

**Not tested on a real VPS or domain.** Do not accept live orders until these checks pass.
