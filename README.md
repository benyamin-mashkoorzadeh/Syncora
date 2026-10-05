# Syncora

Syncora is a focused collaboration and Scrum application built with a Laravel REST API, a Next.js App Router frontend, MySQL, Sanctum session authentication, and Laravel Reverb.

## Repository

```text
syncora/
├── backend/   # Laravel API, domain rules, persistence, broadcasting
├── frontend/  # Next.js application UI
└── AGENTS.md  # Product and architecture rules
```

Laravel is the source of truth for authentication, authorization, validation, persisted collaboration data, and notification state. Next.js owns presentation and transient interaction state. Reverb delivers authorized updates after durable changes have been committed.

## Requirements

- PHP 8.3 or newer with the extensions required by Laravel and image validation
- Composer
- Node.js 20.9 or newer and npm
- MySQL
- A process supervisor for Reverb and queue workers in production
- Redis when using queued broadcasts, shared cache/session storage, or multi-instance Reverb scaling

## Local setup

```bash
cd backend
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate
php artisan storage:link
```

Set the local MySQL credentials in `backend/.env`. Keep `FRONTEND_URL`, `CORS_ALLOWED_ORIGINS`, and `SANCTUM_STATEFUL_DOMAINS` aligned with the frontend origin.

```bash
cd ../frontend
cp .env.example .env.local
npm install
```

Run the stack in three terminals:

```bash
cd backend && php artisan serve
cd backend && php artisan reverb:start
cd frontend && npm run dev
```

The frontend defaults to `http://localhost:3000`, the API to `http://localhost:8000/api/v1`, and Reverb to `ws://localhost:8080`.

## Public avatar storage

Profile images use Laravel's `public` filesystem disk and are stored under `storage/app/public/avatars`. Run `php artisan storage:link` anywhere the local public disk is used. In production, persist and back up this directory or configure the public disk for durable object storage. `APP_URL` must be the public backend URL so API avatar URLs are correct.

Uploads accept JPEG, PNG, and WebP images up to 2 MB. The web server and PHP upload limits must be at least that large.

## Production configuration

Create environment files on the target platform; never commit them. At minimum:

- Set `APP_ENV=production`, `APP_DEBUG=false`, a generated `APP_KEY`, the public HTTPS `APP_URL`, and `FRONTEND_URL`.
- Set `CORS_ALLOWED_ORIGINS` to the exact comma-separated frontend origins. Do not use `*` with credentialed requests.
- Set `SANCTUM_STATEFUL_DOMAINS` to frontend hostnames, including non-standard ports when applicable.
- Use `SESSION_SECURE_COOKIE=true` under HTTPS. For same-site subdomains, set the shared `SESSION_DOMAIN` when required and normally retain `SESSION_SAME_SITE=lax`. Truly cross-site deployments require `SESSION_SAME_SITE=none` with secure cookies and deliberate CORS review.
- Use production MySQL credentials and durable session/cache drivers appropriate to the platform. Redis is recommended for multi-instance deployments.
- Generate unique `REVERB_APP_ID`, `REVERB_APP_KEY`, and secret `REVERB_APP_SECRET`. Configure the public WebSocket host/port/scheme separately from `REVERB_SERVER_HOST` and `REVERB_SERVER_PORT`, and restrict `REVERB_ALLOWED_ORIGINS` to frontend hosts.
- Set frontend `NEXT_PUBLIC_BACKEND_URL`, `NEXT_PUBLIC_API_URL`, and public Reverb variables at build time. Every `NEXT_PUBLIC_*` value is visible to browsers and must not contain secrets.
- Configure trusted reverse proxies at the hosting layer so Laravel receives the correct HTTPS host and scheme. Only trust known proxy addresses.

Deploy the Laravel public web root from `backend/public`, then run:

```bash
composer install --no-dev --classmap-authoritative
php artisan migrate --force
php artisan storage:link
php artisan optimize
```

Build and serve the frontend:

```bash
cd frontend
npm ci
npm run build
npm run start
```

Run `php artisan reverb:start` under a supervisor. The supplied local configuration uses `QUEUE_CONNECTION=sync`, so broadcasts execute inline after committed transactions. If production uses Redis or another asynchronous queue, supervise `php artisan queue:work` and restart workers during deployments. Enable Reverb scaling only with a correctly configured shared Redis service.

Health endpoints are available at Laravel `/up` and `/api/v1/health`. Configure TLS, request-size limits, process restarts, database backups, uploaded-avatar backups, log collection, and health monitoring at the platform level.

## Routes

Authenticated workspace URLs are canonical without an implementation prefix:

```text
/{workspaceSlug}
/{workspaceSlug}/projects/{projectSlug}
/{workspaceSlug}/projects/{projectSlug}/board
/{workspaceSlug}/projects/{projectSlug}/chat
```

Legacy `/w/*` links permanently redirect to the canonical route. Laravel API routes remain under `/api/v1/workspaces/*`.

## Quality checks

```bash
cd backend
php artisan test --compact
vendor/bin/pint --format agent

cd ../frontend
npm run lint
npm run build
```
