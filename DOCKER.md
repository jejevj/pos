# Docker — one stack, one port

This repo ships a Docker Compose stack that runs everything together: the Vue SPA, the Laravel API, Postgres, Redis, and WAHA (WhatsApp gateway). Only **one** port is published to the host — an nginx reverse proxy that fronts every other service on the internal `app-net` network.

## TL;DR

```bash
cp .env.docker.example .env.docker
cp .env.docker.example .env          # compose reads this for ${VAR} expansion

# Fill in POSTGRES_PASSWORD, WAHA_API_KEY, APP_KEY (see below)
# Generate APP_KEY once and paste it into both files:
docker compose run --rm --no-deps backend php artisan key:generate --show

docker compose up -d --build
# → open http://localhost:8080
```

## Why one port?

The default `APP_PORT=8080` is the only port mapped to your host. The proxy routes:

| Path prefix | Goes to | Notes |
| --- | --- | --- |
| `/api/*`, `/sanctum/*`, `/storage/*`, `/up`, `/api/documentation` | `backend:8080` | Laravel + Swagger |
| `/waha/*` | `waha:3000` | REST + WebSocket; prefix is stripped |
| everything else | `frontend:80` | Vue SPA |

Postgres, Redis, WAHA, and the PHP-FPM backend are reachable only from inside the Docker network. That's good for security (no exposed DBs) and good for the "Docker for everything in one piece" feel you asked for.

Need direct access during debugging? Add a `docker-compose.override.yml` that publishes extra ports. The base file stays clean.

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

# psql shell
docker compose exec postgres psql -U postgres saas_app

# Rebuild after pulling code
docker compose up -d --build

# Stop / wipe (data volumes survive `down`; add -v to nuke them)
docker compose down
docker compose down -v   # also drops postgres, redis, waha sessions
```

## Configuration knobs (in `.env.docker` / `.env`)

| Var | Default | Meaning |
| --- | --- | --- |
| `APP_PORT` | `8080` | Host port the proxy listens on |
| `APP_KEY` | *(empty)* | Laravel key — generate once, paste in. Empty means an ephemeral key on each boot. |
| `POSTGRES_PASSWORD` | **required** | Compose refuses to start without it |
| `WAHA_API_KEY` | **required** | Used by both WAHA and the Laravel/Vue clients |
| `REDIS_PASSWORD` | empty | Optional; Redis only listens on the internal network |
| `RUN_MIGRATIONS` | `true` | Run `artisan migrate --force` on boot |
| `RUN_QUEUE_WORKER` | `true` | Start `queue:work` under supervisor inside the backend container |
| `CACHE_STORE` / `SESSION_DRIVER` / `QUEUE_CONNECTION` | `redis` | Switch back to `database` to drop Redis if you prefer |

## Healthchecks

* `postgres` — `pg_isready`
* `redis` — `redis-cli ping`
* `backend` — `curl /up` (Laravel's built-in health endpoint)
* `frontend` — `wget /` (returns the SPA shell)
* `proxy` — `wget /` (proxied SPA)
* `waha` — `wget /api/health` (tolerant; some WAHA versions don't expose it)

`backend` waits for `postgres: service_healthy` and `redis: service_healthy` via `depends_on`, so the boot order is correct.

## Port strategy explained

You asked whether all the internal services need their own host ports. They don't. The stack uses:

- **1 external port** (`APP_PORT`, default 8080) → nginx proxy
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

1. `cp .env.docker.example .env.docker` and `cp .env.docker.example .env`.
2. Fill in `POSTGRES_PASSWORD` and `WAHA_API_KEY`.
3. Run `docker compose run --rm --no-deps backend php artisan key:generate --show`, copy the output into `APP_KEY=` in both files.
4. `docker compose up -d --build`.
5. (First-run only) Once the stack is up, browse to `http://localhost:8080`, log in, and pair WhatsApp from the WhatsApp settings page — WAHA persists the session under the `waha-sessions` volume.

## Keeping local (non-Docker) dev intact

Nothing in `backend-api/`, `frontend-app/`, or `waha/` was changed. The README's `php artisan serve` + `npm run dev` flow still works because `.env` (read by Laravel directly) and the Docker `.env` are separate files — only the latter is consumed by compose. Just don't commit either.
