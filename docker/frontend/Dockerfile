# syntax=docker/dockerfile:1.6
FROM node:22-alpine AS build

WORKDIR /app

# Build-time Vite vars (baked into the bundle)
ARG VITE_API_URL=/api
ARG VITE_WAHA_URL=/waha
ARG VITE_WAHA_API_KEY=change-me
ENV VITE_API_URL=$VITE_API_URL \
    VITE_WAHA_URL=$VITE_WAHA_URL \
    VITE_WAHA_API_KEY=$VITE_WAHA_API_KEY

COPY frontend-app/package.json frontend-app/package-lock.json* ./
RUN npm ci --no-audit --no-fund

COPY frontend-app/ ./
RUN npm run build

FROM nginx:1.27-alpine AS runtime
RUN rm -rf /etc/nginx/conf.d/*
COPY docker/frontend/nginx.conf /etc/nginx/conf.d/default.conf
COPY --from=build /app/dist /usr/share/nginx/html

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=3s --retries=3 \
    CMD wget -qO- http://127.0.0.1/ >/dev/null 2>&1 || exit 1
