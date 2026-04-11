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

- backend/: Symfony backend baseline (bootstrap stage)
- frontend/: React + Vite frontend baseline
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

This repo is currently in bootstrap phase.

- Frontend baseline app is present and served through nginx.
- Backend currently exposes a bootstrap response on /api/.
- Full Symfony skeleton and domain modules will be added step by step.

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

Initialize full Symfony project structure in backend and add the first appointment-oriented API endpoints.
