# Pupilovo: test e-mail / Mailpit

Mailpit is a **development/staging-only** mail catcher. It does not deliver real customer emails. Do not run this overlay on a public production store.

## Portable startup (new server / local)

1. Install Docker Engine and Docker Compose plugin.
2. Clone the Pupilovo repository and configure the project's environment. Do not commit `.env`.
3. For an isolated staging deployment run:

```bash
docker compose -f docker-compose.yml -f docker-compose.mail.yml up -d --build
```

4. On the Docker host open `http://localhost:8025`. For a remote server, use an SSH tunnel (do not expose Mailpit publicly):

```bash
ssh -L 8025:127.0.0.1:8025 USER@SERVER
```

Then open `http://localhost:8025` on your own computer. The test SMTP port 1025 is only reachable by Docker services; the web UI binds to loopback.

To stop the optional Mailpit service:

```bash
docker compose -f docker-compose.yml -f docker-compose.mail.yml stop mailpit
```

**Important:** `docker-compose.yml` is currently a development configuration, not a hardened production stack: it exposes development ports and uses example database passwords. Do not deploy it as a public live store unchanged. Production requires separate secrets, TLS/reverse proxy, backups, secure WordPress configuration, real transactional SMTP, and payment verification. Mailpit is not a substitute for production SMTP.

On the existing development machine, `docker-compose.override.yml` is a local untracked override and already enables Mailpit. Use regular `docker compose up -d` there; do **not** combine that override with `docker-compose.mail.yml` (it would duplicate configuration). For a fresh clone or isolated staging host use the explicit `-f` command above.
