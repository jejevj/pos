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

## Two workflows: production rebuild vs. fast PHP hotfix

The stack supports two day-to-day workflows. Pick based on **what you changed**, not personal preference.

### A. Production rebuild — Dockerfile, composer, or npm changes

Any change to `docker/`, `backend-api/composer.json`, `backend-api/composer.lock`, `frontend-app/package.json`, `frontend-app/package-lock.json`, or the underlying base images requires a real image rebuild. The image is what ships to prod, so this is the default.

```bash
docker compose up -d --build
```

This is also what you run after pulling new code that touches the dependency manifests, or the first time you bring the stack up. No override files involved — the baked-in image is exactly what runs.

### B. Fast hotfix — PHP-only code changes (no dep changes)

For day-to-day Laravel work where you're only touching `.php` / `.blade.php` / route / config / migration files, a full rebuild is overkill. The repo ships `docker-compose.dev.yml`, an **opt-in** override that bind-mounts the Laravel source directories from the host into the running backend container. Edits become visible without rebuilding the image.

```bash
# Bring the stack up with the dev override layered on top of the base file
docker compose -f docker-compose.yml -f docker-compose.dev.yml up -d

# ...edit anything under backend-api/{app,routes,config,database,resources,public,bootstrap/{app,providers}.php}...

# Most edits are picked up immediately (opcache is set to revalidate
# timestamps under the override). When in doubt, clear Laravel's caches:
docker compose exec backend php artisan optimize:clear

# Or force a clean restart of just the backend container:
docker compose restart backend
```

What the override does:

* Mounts `backend-api/{app,routes,config,database,resources,public}` and the two `bootstrap/*.php` entry files over the corresponding paths inside the container.
* Sets `APP_ENV=local`, `APP_DEBUG=true` so cached config/route files are skipped and stack traces show up in responses.
* Flips `opcache.validate_timestamps=1` so PHP re-reads files when their mtime changes — no restart needed for most edits.

What the override deliberately does **not** do:

* It does **not** mount the full `backend-api/` directory. Doing so would hide the `vendor/` tree baked into the image at build time and break Composer's autoloader. If you change `composer.json`/`composer.lock`, you must do a real rebuild (workflow A).
* It does **not** mount `storage/` — that stays on the `backend-storage` named volume so logs, sessions, and uploaded files persist.
* It does **not** change the base `docker-compose.yml`. Production behaviour is unchanged when the override isn't passed on the command line.

To go back to running the baked image (e.g. before a deploy):

```bash
docker compose -f docker-compose.yml up -d --force-recreate backend
```

### When to use which

| You changed... | Workflow |
| --- | --- |
| `backend-api/app/**`, `routes/**`, `config/**`, `database/**`, `resources/**` | B (hotfix) |
| `docker/backend/Dockerfile`, `php.ini`, `nginx.conf`, `supervisord.conf`, `entrypoint.sh` | A (rebuild) |
| `composer.json` / `composer.lock` (added/removed package) | A (rebuild) |
| `frontend-app/**` source | A (rebuild) — the frontend ships built static assets, there's nothing to bind-mount |
| `package.json` / `package-lock.json` | A (rebuild) |
| `.env.docker` / `.env` | Neither — just `docker compose up -d` to pick up env changes |

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

## Troubleshooting

### `failed to resolve source metadata for docker.io/docker/dockerfile:1.6` (or similar)

Older versions of this repo's Dockerfiles started with `# syntax=docker/dockerfile:1.6`, which tells BuildKit to fetch the `docker/dockerfile:1.6` frontend image from Docker Hub before doing anything else. On a build host without internet access to `docker.io`, the whole build fails before a single layer runs:

```
ERROR: failed to resolve source metadata for docker.io/docker/dockerfile:1.6:
  failed to do request: ... dial tcp: network is unreachable
```

Both Dockerfiles now drop that directive and avoid BuildKit-only syntax (`RUN --mount=type=cache`), so they build on the classic builder too — no frontend image pull required. If you still see the error, you're on an older checkout:

```bash
git pull origin ai
docker compose build --no-cache
```

If you absolutely need to use BuildKit on an internet-isolated host, pre-pull the frontend once on a connected machine and `docker save`/`docker load` it onto the target — but you shouldn't need to with the current Dockerfiles.

### `npm error EAI_AGAIN getaddrinfo registry.npmjs.org`

The frontend build hit a transient DNS failure inside the build container. The frontend Dockerfile already configures longer npm retries and pins the registry, so the first thing to do is just retry:

```bash
# Retry the same build — most EAI_AGAIN failures clear on the second pass
docker compose build frontend

# Force a clean rebuild of only the frontend if the cached layer is wedged
docker compose build --no-cache frontend
```

If retries keep failing, your Docker builder is using a broken resolver. Point the Docker daemon at public DNS:

```bash
# /etc/docker/daemon.json on the Docker host
{
  "dns": ["1.1.1.1", "8.8.8.8"]
}
```

```bash
sudo systemctl restart docker     # Linux
# On Docker Desktop: Settings → Docker Engine, paste the same JSON, Apply & Restart.
docker compose build --no-cache frontend
```

You can also verify resolution from inside a transient build container:

```bash
docker run --rm node:22-alpine sh -c 'apk add --no-cache bind-tools >/dev/null && nslookup registry.npmjs.org'
```

### `pecl install redis` or backend image fails to build

Already handled — phpredis now builds from a pinned git tag instead of pecl. If you have a cached half-broken backend layer, force a clean build:

```bash
docker compose build --no-cache backend
```

### Backend can't reach the host Postgres

Test name resolution from inside the backend container:

```bash
docker compose exec backend getent hosts host.docker.internal
docker compose exec backend pg_isready -h host.docker.internal -p 5432 -U postgres
```

If `getent` returns nothing on Linux, your Docker Engine is older than 20.10 — upgrade. If `pg_isready` fails but `getent` works, your host Postgres is bound to `127.0.0.1` only — set `listen_addresses = '*'` and add the Docker bridge subnet (commonly `172.16.0.0/12`) to `pg_hba.conf`.

## Keeping local (non-Docker) dev intact

Nothing in `backend-api/`, `frontend-app/`, or `waha/` was changed. The README's `php artisan serve` + `npm run dev` flow still works because `.env` (read by Laravel directly) and the Docker `.env` are separate files — only the latter is consumed by compose. Just don't commit either.
