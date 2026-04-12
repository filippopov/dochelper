# dochelper

Dochelper is a doctor appointment booking platform built incrementally with a Docker-first workflow.

The platform architecture separates responsibilities across dedicated containers:
- Symfony API backend
- React frontend
- MySQL database
- Nginx reverse proxy
- Cron worker for scheduled jobs

## Project Goal

Allow patients to book appointments with doctors through a web interface, with backend APIs and scheduled tasks supporting appointment lifecycle operations.

## Technology Stack

- PHP 8.3
	- FPM runtime for API requests
	- CLI runtime for scheduled tasks
- Symfony 7.4
- React 18.3.1
- Vite 6.0.5
- Node.js 20
- MySQL 8.0
- Nginx image: nginx:bookworm
- PHPUnit 12.5

## Repository Structure

- backend/: Symfony 7.4 API application
- frontend/: React + Vite client
- docker/: container definitions and config
- docker-compose.yml: local multi-container orchestration

## Services

- nginx
	- Entry point for local app traffic
	- Exposes host port defined by BACKEND_PORT (default 8080)
	- Routes:
		- / -> frontend (Vite dev server)
		- /api/ -> backend PHP runtime

- backend
	- PHP 8.3 FPM container for API handling
	- Mounted from ./backend

- frontend
	- Node 20 container running Vite dev server
	- Mounted from ./frontend
	- Installs dependencies on first startup when node_modules is empty

- db
	- MySQL 8.0
	- Persists data in named Docker volume db_data
	- Host port mapped from DB_PORT (current local default 3307)

- cron
	- PHP 8.3 CLI container
	- Uses docker/cron/crontab for scheduled tasks

## Environment Variables

Template file: .env.example

Typical local values:
- APP_ENV=dev
- APP_SECRET=change_me
- JWT_SECRET_KEY=/var/www/backend/config/jwt/private.pem
- JWT_PUBLIC_KEY=/var/www/backend/config/jwt/public.pem
- JWT_PASSPHRASE=change_me
- MYSQL_ROOT_PASSWORD=root
- MYSQL_DATABASE=dochelper
- MYSQL_USER=dochelper
- MYSQL_PASSWORD=dochelper
- DB_PORT=3307
- BACKEND_PORT=8080

## Quick Start (PowerShell)

1. Create env file (if missing):

```powershell
Copy-Item .env.example .env
```

2. Build images:

```powershell
docker compose build
```

3. Start containers:

```powershell
docker compose up -d
```

4. Check status:

```powershell
docker compose ps
```

## Endpoints

- App via nginx: http://localhost:8080
- API via nginx: http://localhost:8080/api/
- MySQL host port: 3307

### Authentication Endpoints

- POST /api/register
	- Request JSON: {"email":"user@example.com","password":"secret123","roleType":"patient"}
	- Note: public registration is patient-only; doctor role cannot be self-registered
	- Success: 201 Created with user payload
- POST /api/login
	- Request JSON: {"email":"user@example.com","password":"secret123"}
	- Success: 200 OK with access token + refresh token + user payload
- POST /api/token/refresh
	- Request JSON: {"refreshToken":"<token>"}
	- Success: 200 OK with rotated refresh token and new access token
- POST /api/logout
	- Request JSON: {"refreshToken":"<token>"}
	- Header: Authorization: Bearer <access-token>
	- Success: 200 OK and refresh token revoked
- GET /api/me
	- Header: Authorization: Bearer <token>
	- Success: 200 OK with current authenticated user

### Appointment Endpoints

- POST /api/appointments
	- Role: patient
	- Request JSON: {"doctorId":2,"scheduledAt":"2026-04-13T10:00:00+00:00","durationMinutes":30,"reason":"Consultation"}
- GET /api/appointments
	- Role: patient or doctor
	- Returns appointments scoped to authenticated role
- GET /api/appointments/{id}
	- Role: patient or doctor
	- Allowed only for participating patient/doctor
- PATCH /api/appointments/{id}/status
	- Role: doctor (assigned to appointment)
	- Request JSON: {"status":"confirmed"} or {"status":"completed"}
- POST /api/appointments/{id}/cancel
	- Role: patient (owner of appointment)

## Useful Commands

View logs:

```powershell
docker compose logs -f
```

Logs for specific services:

```powershell
docker compose logs -f nginx frontend backend db cron
```

Stop stack:

```powershell
docker compose down --remove-orphans
```

Rebuild a single service:

```powershell
docker compose build frontend
```

## Current Baseline Status

- Full Symfony app bootstrap is in place.
- JWT access + rotating refresh-token flow is implemented.
- Role model supports patient/doctor with patient-only public registration.
- Protected appointment endpoints are implemented with role and ownership guards.
- Frontend includes login/register and automatic access-token refresh on 401.

## Troubleshooting

1. Port conflict on MySQL
- Symptom: bind error for host port 3306
- Fix: set DB_PORT to a free port (for example 3307) in .env

2. Frontend fails to start with rollup optional dependency error
- Symptom: module not found for platform-specific rollup package
- Fix: recreate frontend container and reinstall dependencies

```powershell
docker compose rm -sf frontend
docker compose up -d frontend
```

3. Nginx returns 502 on /
- Usually means frontend container is restarting or not ready yet
- Check:

```powershell
docker compose ps
docker compose logs -f frontend nginx
```

## Next Implementation Step

Add admin-managed doctor creation flow and optional appointment conflict detection.
