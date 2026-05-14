# Docker — one stack, one port

This repo ships a Docker Compose stack that runs the Vue SPA, the Laravel API, and Redis. Only **one** port is published to the host — an nginx reverse proxy that fronts every other service on the internal `app-net` network.

**Postgres and WAHA are NOT bundled by default.**

* Postgres → the stack expects you to point `DB_HOST` at an existing Postgres reachable from the Docker host. See [Database options](#database-options).
* WAHA (WhatsApp gateway) → off by default; enable later via a profile or by pointing the backend at an external WAHA instance. See [WhatsApp / WAHA](#whatsapp--waha).

## TL;DR

The shipped `.env.docker.example` already has working defaults for the
existing host Postgres (`host.docker.internal:5432/pos_sc`,
`postgres / qwert12345!`) — just copy it and you're done with the DB.

```bash
cp .env.docker.example .env.docker
cp .env.docker.example .env          # compose reads this for ${VAR} expansion

# Generate APP_KEY once and paste the output into APP_KEY= in BOTH files.
# (Works even before WAHA is configured; key generation has no required deps.)
docker compose run --rm --no-deps backend php artisan key:generate --show

docker compose up -d --build
# → open http://localhost:9080
```

WAHA stays off until you opt in (see [WhatsApp / WAHA](#whatsapp--waha)). Hitting `/waha/*` in the meantime returns a 502 from the proxy — the rest of the app is unaffected.

## Why one port?

The default `APP_PORT=9080` is the only port mapped to your host. The proxy routes:

| Path prefix | Goes to | Notes |
| --- | --- | --- |
| `/api/*`, `/sanctum/*`, `/storage/*`, `/up`, `/api/documentation` | `backend:8080` | Laravel + Swagger |
| `/waha/*` | `waha:3000` (optional) | REST + WebSocket; prefix is stripped. 502 when WAHA isn't running. |
| everything else | `frontend:80` | Vue SPA |

Redis and the PHP-FPM backend are reachable only from inside the Docker network. Postgres lives outside the compose stack by default (see [Database options](#database-options)); WAHA is optional (see [WhatsApp / WAHA](#whatsapp--waha)).

Need direct access during debugging? Add a `docker-compose.override.yml` that publishes extra ports. The base file stays clean.

## Database options

### Default — external Postgres (recommended)

The backend container is configured to dial whatever you put in `DB_HOST` / `DB_PORT` / `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD`. Defaults in `.env.docker.example`:

```env
DB_CONNECTION=pgsql
DB_HOST=host.docker.internal
DB_PORT=5432
DB_DATABASE=pos_sc
DB_USERNAME=postgres
DB_PASSWORD=qwert12345!
```

`host.docker.internal` (not `127.0.0.1` — the loopback inside the container is the container itself, not the host) works thanks to `extra_hosts: host.docker.internal:host-gateway` on the `backend` service. That mapping is required on Linux and is a no-op on Docker Desktop. If your existing Postgres container is on a named Docker network you can also attach the stack to that network instead, but `host.docker.internal` is the path of least surprise.

Boot the stack normally:

```bash
docker compose up -d --build
```

### Optional — bundled Postgres (profile: `internal-db`)

If you'd rather have compose manage the database, a Postgres service is defined behind the `internal-db` profile. It does NOT start unless you opt in.

```env
# in .env / .env.docker
DB_HOST=postgres               # the service name
DB_DATABASE=${POSTGRES_DB}     # keep these aligned
DB_USERNAME=${POSTGRES_USER}
DB_PASSWORD=${POSTGRES_PASSWORD}

POSTGRES_DB=pos_sc
POSTGRES_USER=postgres
POSTGRES_PASSWORD=qwert12345!  # required when using this profile
```

```bash
docker compose --profile internal-db up -d --build
```

## Files added

```
.dockerignore
.env.docker.example
DOCKER.md
docker-compose.yml
docker/
  backend/
    Dockerfile           # PHP 8.3-fpm + nginx + supervisor (PG, Redis, GD, opcache)
    nginx.conf
    php.ini
    www.conf
    supervisord.conf     # php-fpm + nginx + queue:work
    entrypoint.sh        # waits for PG, runs migrate, caches config
  frontend/
    Dockerfile           # node 22 build → nginx:alpine serve
    nginx.conf           # SPA fallback to index.html
  proxy/
    nginx.conf           # the only externally-facing nginx
```

Existing files (`backend-api/`, `frontend-app/`, `waha/docker-compose.yml`) are untouched, so the local dev workflow described in `README.md` keeps working.

## Common operations

```bash
# View status / logs
docker compose ps
docker compose logs -f backend
docker compose logs -f proxy

# Run an artisan command
docker compose exec backend php artisan tinker
docker compose exec backend php artisan migrate:status

# Re-seed
docker compose exec backend php artisan db:seed

# psql shell (only when the internal-db profile is active)
docker compose --profile internal-db exec postgres psql -U postgres pos_sc

# Rebuild after pulling code
docker compose up -d --build

# Stop / wipe (data volumes survive `down`; add -v to nuke them)
docker compose down
docker compose down -v   # also drops postgres, redis, waha sessions
```

## Configuration knobs (in `.env.docker` / `.env`)

| Var | Default | Meaning |
| --- | --- | --- |
| `APP_PORT` | `9080` | Host port the proxy listens on |
| `APP_KEY` | *(empty)* | Laravel key — generate once, paste in. Empty means an ephemeral key on each boot. |
| `DB_HOST` | `host.docker.internal` | Where the backend dials Postgres |
| `DB_PORT` | `5432` | Postgres port on `DB_HOST` |
| `DB_DATABASE` | `pos_sc` | Database name |
| `DB_USERNAME` | `postgres` | Database user |
| `DB_PASSWORD` | `qwert12345!` | Override in `.env.docker` for any other deployment |
| `POSTGRES_PASSWORD` | required only with `--profile internal-db` | Password for the bundled Postgres |
| `WAHA_ENABLED` | `false` | Flip to `true` after WAHA is reachable |
| `WAHA_API_KEY` | empty | Only required when `WAHA_ENABLED=true` |
| `WAHA_BASE_URL` | `http://waha:3000` | Internal service name by default; override for an external WAHA |
| `REDIS_PASSWORD` | empty | Optional; Redis only listens on the internal network |
| `RUN_MIGRATIONS` | `true` | Run `artisan migrate --force` on boot |
| `RUN_QUEUE_WORKER` | `true` | Start `queue:work` under supervisor inside the backend container |
| `CACHE_STORE` / `SESSION_DRIVER` / `QUEUE_CONNECTION` | `redis` | Switch back to `database` to drop Redis if you prefer |

## Healthchecks

* `redis` — `redis-cli ping`
* `backend` — `curl /up` (Laravel's built-in health endpoint)
* `frontend` — `wget /` (returns the SPA shell)
* `proxy` — `wget /` (proxied SPA)
* `waha` (only with `--profile whatsapp`) — `wget /api/health` (tolerant; some WAHA versions don't expose it)
* `postgres` (only with `--profile internal-db`) — `pg_isready`

`backend` waits for `redis: service_healthy` via `depends_on`. The external Postgres is *not* gated by Docker — the entrypoint script polls it with `pg_isready` before running migrations.

## Port strategy explained

You asked whether all the internal services need their own host ports. They don't. The stack uses:

- **1 external port** (`APP_PORT`, default 9080) → nginx proxy
- **0 external ports** for backend / postgres / redis / waha / frontend (Docker DNS resolves them by service name on `app-net`)

Tradeoffs:

- ✅ Smaller attack surface, no port collisions, single URL for the team.
- ✅ Frontend `VITE_API_URL=/api` and `VITE_WAHA_URL=/waha` — no CORS pain because everything is same-origin.
- ⚠️ If you want to hit Postgres from a desktop tool (DBeaver/TablePlus), add a port mapping in a local override file — don't publish it in the base compose file.

## What was NOT verified

Docker is not available in the sandbox where this scaffolding was generated, so I could not run `docker compose build` or `docker compose up` end-to-end. Specifically not validated:

- The exact tag list for `devlikeapro/waha` and which env var names the current image accepts (`WHATSAPP_API_KEY` vs `WAHA_API_KEY`). The compose file sets `WHATSAPP_API_KEY`, which matches WAHA Core docs; adjust if your image rejects it.
- That the WAHA `/api/health` endpoint exists on the image tag you pull — the healthcheck is intentionally lenient (`|| exit 0`) so a 404 won't mark the container unhealthy.
- The PHP `redis` PECL extension build on Alpine 3.20 — this is the standard recipe but pin to a specific PHP image tag if you want reproducibility.
- Frontend build — make sure `frontend-app/package-lock.json` is committed (it is, per the file listing) so `npm ci` succeeds.

## Manual steps you still need to do

1. `cp .env.docker.example .env.docker` and `cp .env.docker.example .env`. The DB defaults already match your existing host Postgres (`pos_sc` on `host.docker.internal:5432`) — no edits needed unless you want to change them. WAHA is off by default; you can leave its values blank for now.
2. Make sure the `pos_sc` database exists on that Postgres. If not: `psql -h 127.0.0.1 -U postgres -c 'CREATE DATABASE pos_sc;'`.
3. Run `docker compose run --rm --no-deps backend php artisan key:generate --show`, copy the output into `APP_KEY=` in both files.
4. `docker compose up -d --build`.
5. (Linux only) Confirm `host.docker.internal` resolves inside the container: `docker compose exec backend getent hosts host.docker.internal`. If your Postgres listens only on `127.0.0.1`, change `listen_addresses` to `*` (or the docker bridge IP) and update `pg_hba.conf` to allow the Docker bridge subnet — otherwise the backend will get `connection refused`.
6. (Optional, later) Wire up WhatsApp — see [WhatsApp / WAHA](#whatsapp--waha).

## WhatsApp / WAHA

WAHA is **off by default**. The stack boots, key generation runs, and the app works fine without it. `/waha/*` returns 502 from the proxy until you turn WAHA on. Two options when you're ready:

### Option A — bundled WAHA (profile: `whatsapp`)

In `.env.docker` and `.env`:

```env
WAHA_ENABLED=true
WAHA_API_KEY=<some-strong-random-string>
WAHA_BASE_URL=http://waha:3000      # internal service name; this is the default
```

Then:

```bash
docker compose --profile whatsapp up -d
# pair the device via the SPA's WhatsApp settings page
```

The bundled WAHA persists its session under the `waha-sessions` volume.

### Option B — external WAHA

Run WAHA wherever you like (another host, a managed service, the existing `waha/docker-compose.yml`, etc.) and point the backend at it:

```env
WAHA_ENABLED=true
WAHA_API_KEY=<key-of-your-external-waha>
WAHA_BASE_URL=http://your-waha-host:3000
```

`docker compose up -d` (no `--profile whatsapp`) — the bundled WAHA stays disabled.

> Restart the backend after toggling WAHA env vars: `docker compose up -d backend`.

## Cloudflare Tunnel

The proxy publishes a single port, so the tunnel needs **one** hostname pointed at `http://localhost:9080`. Path routing (`/api`, `/sanctum`, `/storage`, `/waha`, `/`) is already handled by the in-stack nginx — no separate `frontend.*` / `api.*` / `waha.*` hostnames required.

`config.yml` example for `cloudflared`:

```yaml
ingress:
  - hostname: pos.your-domain.tld
    service: http://localhost:9080
  - service: http_status:404
```

Then update **both** `.env.docker` and `.env` so Laravel and Sanctum trust the public hostname:

```env
APP_URL=https://pos.your-domain.tld
FRONTEND_URL=https://pos.your-domain.tld
SANCTUM_STATEFUL_DOMAINS=pos.your-domain.tld
```

Frontend `VITE_API_URL` / `VITE_WAHA_URL` stay as relative paths (`/api`, `/waha`) — same-origin, no CORS gymnastics, no rebuild needed if the domain changes.

## Keeping local (non-Docker) dev intact

Nothing in `backend-api/`, `frontend-app/`, or `waha/` was changed. The README's `php artisan serve` + `npm run dev` flow still works because `.env` (read by Laravel directly) and the Docker `.env` are separate files — only the latter is consumed by compose. Just don't commit either.
