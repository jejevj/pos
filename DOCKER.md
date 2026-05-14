# Docker — POS stack + stand-alone WAHA

This repo ships a Docker Compose stack that runs the Vue SPA, the Laravel API, and Redis behind a single nginx proxy. WAHA (the WhatsApp gateway) is defined in the same compose file but runs as its **own** service on its own port, fronted by its own Cloudflare Tunnel hostname.

| Port (host) | Service | Default tunnel hostname |
| --- | --- | --- |
| `${APP_PORT:-9080}` → proxy:80 | Vue SPA + Laravel API + Sanctum | `pos.your-domain.tld` |
| `127.0.0.1:${WAHA_PORT:-9081}` → waha:3000 | WAHA WhatsApp gateway (REST + WebSocket) | `waha.ourtestcloud.my.id` |

**Postgres and WAHA are NOT started by default.**

* Postgres → the stack expects you to point `DB_HOST` at an existing Postgres reachable from the Docker host. See [Database options](#database-options).
* WAHA → off by default; start it on demand with `docker compose up -d waha`. See [WhatsApp / WAHA](#whatsapp--waha).

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

WAHA stays off until you start it explicitly (see [WhatsApp / WAHA](#whatsapp--waha)). Hitting `/waha/*` on the POS proxy returns **410 Gone** — that path is deprecated; WAHA now lives on its own hostname.

## Proxy routing

The `APP_PORT=9080` proxy fronts the POS app only. The proxy routes:

| Path prefix | Goes to | Notes |
| --- | --- | --- |
| `/api/*`, `/sanctum/*`, `/storage/*`, `/up`, `/api/documentation` | `backend:8080` | Laravel + Swagger |
| `/waha/*` | — | **Deprecated.** Returns `410 Gone`. Use the WAHA hostname directly. |
| everything else | `frontend:80` | Vue SPA |

Redis and the PHP-FPM backend are reachable only from inside the Docker network. Postgres lives outside the compose stack by default (see [Database options](#database-options)). WAHA is on its own port (`127.0.0.1:9081`) and tunneled separately — see [WhatsApp / WAHA](#whatsapp--waha).

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
| `WAHA_PORT` | `9081` | Host port for the stand-alone WAHA service. Bound to `127.0.0.1` — exposed publicly via Cloudflare Tunnel only. |
| `APP_KEY` | *(empty)* | Laravel key — generate once, paste in. Empty means an ephemeral key on each boot. |
| `DB_HOST` | `host.docker.internal` | Where the backend dials Postgres |
| `DB_PORT` | `5432` | Postgres port on `DB_HOST` |
| `DB_DATABASE` | `pos_sc` | Database name |
| `DB_USERNAME` | `postgres` | Database user |
| `DB_PASSWORD` | `qwert12345!` | Override in `.env.docker` for any other deployment |
| `POSTGRES_PASSWORD` | required only with `--profile internal-db` | Password for the bundled Postgres |
| `WAHA_ENABLED` | `false` | Flip to `true` after WAHA is reachable (backend side) |
| `WAHA_API_KEY` | empty | REQUIRED when `WAHA_ENABLED=true`. Generate with `openssl rand -hex 32`. |
| `WAHA_BASE_URL` | `https://waha.ourtestcloud.my.id` | Public Cloudflare-tunneled hostname by default. Override to `http://waha:3000` for in-cluster calls. |
| `WAHA_DASHBOARD_USERNAME` / `WAHA_DASHBOARD_PASSWORD` | empty | Basic-auth for the WAHA dashboard. Set before exposing WAHA publicly. |
| `WAHA_SWAGGER_USERNAME` / `WAHA_SWAGGER_PASSWORD` | empty | Basic-auth for WAHA's Swagger UI. |
| `WAHA_PUBLIC_HOSTNAME` / `WAHA_PUBLIC_SCHEMA` | `waha.ourtestcloud.my.id` / `https` | What WAHA advertises about itself in webhooks/Swagger. Match the tunnel hostname. The internal listen port (`WHATSAPP_API_PORT`) is pinned at `3000` and is **not** the public port — don't override it to 443. |
| `WAHA_ENGINE` | `WEBJS` | `WEBJS`, `NOWEB`, or `GOWS`. |
| `VITE_WAHA_ENABLED` | `false` | Frontend-side opt-in. When `false` or `VITE_WAHA_API_KEY` is empty/placeholder, the SPA does NOT open the WAHA WebSocket or call the WAHA REST API, and the WhatsApp view shows an "unconfigured" banner. Baked at build time — change requires `docker compose build frontend`. |
| `VITE_WAHA_API_KEY` | empty | Must be a real, non-placeholder key (NOT `change-me`) for the frontend to talk to WAHA. Baked at build time. |
| `REDIS_PASSWORD` | empty | Optional; Redis only listens on the internal network |
| `RUN_MIGRATIONS` | `true` | Run `artisan migrate --force` on boot |
| `RUN_QUEUE_WORKER` | `true` | Start `queue:work` under supervisor inside the backend container |
| `CACHE_STORE` / `SESSION_DRIVER` / `QUEUE_CONNECTION` | `redis` | Switch back to `database` to drop Redis if you prefer |

## Healthchecks

* `redis` — `redis-cli ping`
* `backend` — `curl /up` (Laravel's built-in health endpoint)
* `frontend` — `wget /` (returns the SPA shell)
* `proxy` — `wget /` (proxied SPA)
* `waha` (only when you ran `docker compose up -d waha`) — `wget /api/health` (tolerant; some WAHA versions don't expose it)
* `postgres` (only with `--profile internal-db`) — `pg_isready`

`backend` waits for `redis: service_healthy` via `depends_on`. The external Postgres is *not* gated by Docker — the entrypoint script polls it with `pg_isready` before running migrations.

## Port strategy explained

The stack uses:

- **1 public port** for POS (`APP_PORT`, default 9080) → nginx proxy
- **1 localhost-only port** for WAHA (`WAHA_PORT`, default 9081, bound to `127.0.0.1`) → tunneled via Cloudflare to `waha.ourtestcloud.my.id`
- **0 external ports** for backend / postgres / redis / frontend (Docker DNS resolves them by service name on `app-net`)

Tradeoffs:

- ✅ Smaller attack surface; backend, frontend, redis, postgres stay on the internal network.
- ✅ WAHA is operated, restarted, and tunneled independently of the POS app — no proxy reloads or full stack restarts when fiddling with WhatsApp.
- ✅ Frontend `VITE_API_URL=/api` is same-origin; `VITE_WAHA_URL` is an absolute URL to the WAHA hostname (CORS handled inside WAHA).
- ⚠️ If you want to hit Postgres or WAHA from a desktop tool, add a port mapping in a local override file or use the existing localhost binding via an SSH tunnel.

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

## Outlet provisioning

New outlets are now fully provisioned automatically when created through the API (`POST /api/outlets`) or any Eloquent `Outlet::create(...)` path: the Postgres schema, every per-outlet table (`outlet_users`, RBAC, transactions, menu, station, bahan baku, stock opname, promo, membership, HR, shift, kasbon, purchase/expense, employee beverage) is created, default roles/permissions are seeded, and the creating global user is auto-mapped as an `outlet_users` row with the `owner` role so they can immediately clock in and manage that outlet.

For outlets that existed **before** this change (e.g. the original "Outlet Pusat"), or whenever you want a self-healing re-run, use:

```bash
# Re-provision a single outlet AND map its global owner as outlet_user owner.
# Use this to fix the NOT_OUTLET_EMPLOYEE error on an existing outlet.
docker compose exec backend php artisan outlets:provision --outlet-id=1 --with-owner

# Re-provision every outlet (idempotent — does nothing where tables already exist)
docker compose exec backend php artisan outlets:provision --with-owner

# Map a specific global user (by users.id) as the owner of outlet 1
docker compose exec backend php artisan outlets:provision --outlet-id=1 --owner-user-id=1
```

Notes:
- The command is idempotent — it uses `CREATE TABLE IF NOT EXISTS` / `ADD COLUMN IF NOT EXISTS` everywhere and `updateOrInsert` for seeded roles/permissions. Safe to re-run.
- Superadmin is **not** silently mapped to every outlet. They get an `outlet_users` row only for outlets they personally created, or when you explicitly pass their `users.id` via `--owner-user-id`.
- The legacy `outlets:create-schemas`, `outlets:create-rbac-tables`, `outlets:seed-rbac`, `outlets:create-transaction-tables`, `outlets:create-menu-tables`, `outlets:create-bahan-baku-tables`, `outlets:create-station-tables`, `outlets:create-stock-opname-tables`, `outlets:create-promo-tables`, `outlets:create-membership-tables`, `outlet:create-hr-tables`, `outlet:create-shift-tables`, `create:kasbon-tables`, `create:purchase-expense-tables`, and `outlets:create-employee-beverage-tables` commands still exist for backward compatibility, but you no longer need to chain them by hand.

## WhatsApp / WAHA

WAHA is **off by default**. The POS stack boots, key generation runs, and the app works fine without it. The proxy's old `/waha/*` route now returns `410 Gone`; WAHA lives on its own hostname.

### Running WAHA

WAHA is defined in `docker-compose.yml` as a stand-alone service (no profile). It binds **only to `127.0.0.1:${WAHA_PORT:-9081}`** so the only thing on the host that can talk to it is `cloudflared` (or you, locally). To start / stop it independently of the POS app:

```bash
docker compose up -d waha       # start (or restart) just WAHA
docker compose logs -f waha     # follow logs
docker compose stop waha        # stop without touching POS
```

Sessions persist under the `waha-sessions` volume; media under `waha-media`.

### Required env

In `.env.docker` and `.env`, before running `docker compose up -d waha`:

```env
WAHA_PORT=9081
WAHA_API_KEY=<openssl rand -hex 32>
WAHA_DASHBOARD_USERNAME=<pick one>
WAHA_DASHBOARD_PASSWORD=<strong random>
WAHA_SWAGGER_USERNAME=<pick one>
WAHA_SWAGGER_PASSWORD=<strong random>
WAHA_PUBLIC_HOSTNAME=waha.ourtestcloud.my.id
WAHA_PUBLIC_SCHEMA=https
WAHA_ENGINE=WEBJS
```

> ⚠️ Do **not** set `WHATSAPP_API_PORT=443` in your `.env.docker` (or anywhere else WAHA reads its env from). That variable is WAHA's **internal listen port** and must stay `3000` — the container-side of the `127.0.0.1:${WAHA_PORT:-9081}:3000` mapping. The public port (443) is already implied by `WAHA_PUBLIC_SCHEMA=https` and the Cloudflare Tunnel that fronts WAHA.

> Do NOT commit real API keys or dashboard credentials. The example file ships with blanks.

### Wiring POS to WAHA

The backend reads `WAHA_*` env vars; the frontend reads `VITE_WAHA_*`. To turn the integration on:

```env
# .env.docker (read by the backend at runtime)
WAHA_ENABLED=true
WAHA_BASE_URL=https://waha.ourtestcloud.my.id   # the public tunnel hostname (default)
WAHA_API_KEY=<same value as above>

# .env (read by docker compose at build / up time)
VITE_WAHA_ENABLED=true
VITE_WAHA_URL=https://waha.ourtestcloud.my.id
VITE_WAHA_API_KEY=<same value as above>
```

Then:

```bash
docker compose up -d waha                # bring WAHA up
docker compose build frontend            # bake the new VITE_WAHA_* values in
docker compose up -d backend frontend    # apply backend env and ship the new bundle
```

> Vite envs are baked at **build time**. Flipping `VITE_WAHA_ENABLED=false` → `true`, or rotating `VITE_WAHA_API_KEY`, always requires a frontend rebuild + redeploy. The backend just needs `docker compose up -d backend` to pick up new env values.

If you'd rather keep POS-to-WAHA traffic inside the docker network (and skip the Cloudflare tunnel for backend → WAHA calls), set `WAHA_BASE_URL=http://waha:3000` instead. Both services join `app-net`, so the service-name resolution works. The frontend still has to use the public hostname because the browser is outside the network.

## Cloudflare Tunnel

Two tunnel hostnames now — one for POS, one for WAHA. Both point at `localhost` ports on the Docker host.

`config.yml` example for `cloudflared`:

```yaml
ingress:
  - hostname: pos.your-domain.tld
    service: http://localhost:9080
  - hostname: waha.ourtestcloud.my.id
    service: http://localhost:9081
  - service: http_status:404
```

If you manage the tunnel from the Cloudflare One dashboard instead of a `config.yml`, add two **Public Hostname** rows on the tunnel:

| Subdomain | Domain | Service |
| --- | --- | --- |
| `pos` | `your-domain.tld` | `HTTP` → `localhost:9080` |
| `waha` | `ourtestcloud.my.id` | `HTTP` → `localhost:9081` |

Then update `.env.docker` and `.env` so Laravel and Sanctum trust the public POS hostname:

```env
APP_URL=https://pos.your-domain.tld
FRONTEND_URL=https://pos.your-domain.tld
SANCTUM_STATEFUL_DOMAINS=pos.your-domain.tld
```

Frontend `VITE_API_URL` stays relative (`/api`) — same-origin POS API. `VITE_WAHA_URL` is the WAHA tunnel hostname (cross-origin, but WAHA handles CORS itself).

## Troubleshooting

### WAHA `curl http://127.0.0.1:9081/dashboard` → `Connection reset by peer`

Symptom: `docker compose ps waha` shows the container running, but every request to `http://127.0.0.1:9081/dashboard` or `http://127.0.0.1:9081/api/server/status` is reset immediately. `docker exec waha env | grep WHATSAPP_API_PORT` prints `443`.

Cause: `WHATSAPP_API_PORT` is WAHA's **internal listen port**, not an advertisement-only value. Older copies of `.env.docker` (and an earlier version of this compose file) set `WHATSAPP_API_PORT=443` thinking it was the public-facing port. WAHA then tries to bind 443 inside the container, fails (or binds the wrong port), and the `127.0.0.1:9081 → container:3000` host mapping has nothing to forward to — the kernel RSTs the connection.

Fix:

```bash
# 1. Edit /path/to/pos/.env.docker (and /path/to/pos/.env if you copied it) —
#    remove WAHA_PUBLIC_PORT entirely, and make sure WHATSAPP_API_PORT is
#    NOT pinned to 443 anywhere. Or just recopy the fresh example:
cp .env.docker.example .env.docker
cp .env.docker.example .env
# (then re-paste your real WAHA_API_KEY / dashboard credentials)

# 2. Ensure the override is gone for the running container.
#    If you must set it explicitly, set it to 3000:
#      WHATSAPP_API_PORT=3000

# 3. Recreate just the WAHA container so it picks up the new env.
docker compose up -d --force-recreate waha

# 4. Verify WAHA is now listening on container port 3000 and the host bind works.
docker compose exec waha sh -c 'wget -qO- http://127.0.0.1:3000/api/server/status || echo INTERNAL_FAIL'
curl -sS http://127.0.0.1:9081/api/server/status
curl -sSI http://127.0.0.1:9081/dashboard
```

Public access via the Cloudflare Tunnel keeps working unchanged: `waha.ourtestcloud.my.id` (https, 443) → `localhost:9081` → `waha:3000`. WAHA still advertises itself as `https://waha.ourtestcloud.my.id` because `WAHA_PUBLIC_HOSTNAME` and `WAHA_PUBLIC_SCHEMA=https` are unchanged.

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
