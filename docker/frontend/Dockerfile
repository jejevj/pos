FROM node:22-alpine AS build

WORKDIR /app

# Build-time Vite vars (baked into the bundle).
# VITE_WAHA_ENABLED defaults to "false" so the bundled frontend does NOT try to
# open a WAHA WebSocket / hit the WAHA REST API with a placeholder API key.
# Set VITE_WAHA_ENABLED=true (and a real VITE_WAHA_API_KEY) at build time to
# turn the WhatsApp UI on. See DOCKER.md.
ARG VITE_API_URL=/api
ARG VITE_WAHA_ENABLED=false
ARG VITE_WAHA_URL=/waha
ARG VITE_WAHA_API_KEY=
ENV VITE_API_URL=$VITE_API_URL \
    VITE_WAHA_ENABLED=$VITE_WAHA_ENABLED \
    VITE_WAHA_URL=$VITE_WAHA_URL \
    VITE_WAHA_API_KEY=$VITE_WAHA_API_KEY

# npm hardening: pin the registry, retry transient DNS/network failures
# longer than the default 60s window. EAI_AGAIN during `npm ci` is almost
# always a transient resolver hiccup; aggressive retries fix it without
# touching the host or Docker daemon DNS config.
ENV NPM_CONFIG_REGISTRY=https://registry.npmjs.org/ \
    NPM_CONFIG_FETCH_RETRIES=5 \
    NPM_CONFIG_FETCH_RETRY_MINTIMEOUT=20000 \
    NPM_CONFIG_FETCH_RETRY_MAXTIMEOUT=120000 \
    NPM_CONFIG_FETCH_TIMEOUT=600000 \
    NPM_CONFIG_AUDIT=false \
    NPM_CONFIG_FUND=false

COPY frontend-app/package.json frontend-app/package-lock.json* ./

# Plain `npm ci` — no BuildKit cache mount, so this works on the classic
# builder too. The retry/timeout knobs above already cushion transient
# registry hiccups; the tradeoff is a slightly slower first build.
RUN npm ci --prefer-offline --no-audit --no-fund

COPY frontend-app/ ./
RUN npm run build

FROM nginx:1.27-alpine AS runtime
# tzdata lets the TZ env var take effect, so nginx $time_local in access
# logs renders in the chosen zone (defaults to Asia/Jakarta via compose).
RUN apk add --no-cache tzdata
RUN rm -rf /etc/nginx/conf.d/*
COPY docker/frontend/nginx.conf /etc/nginx/conf.d/default.conf
COPY --from=build /app/dist /usr/share/nginx/html

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=3s --retries=3 \
    CMD wget -qO- http://127.0.0.1/ >/dev/null 2>&1 || exit 1
