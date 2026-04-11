# Copilot Instructions for dochelper

## Project Context

dochelper is a doctor appointment booking platform built step by step.

Architecture is Docker-first and split into separate services:
- Symfony backend API (PHP-FPM)
- React frontend (Vite dev server)
- MySQL database
- Nginx reverse proxy
- Cron worker for scheduled jobs

## Locked Technology Versions

Use these versions unless explicitly asked to change:
- PHP 8.3
- Symfony 7.4
- React 18.3.1
- Vite 6.0.5
- Node.js 20
- MySQL 8.0
- Nginx image: nginx:bookworm
- PHPUnit 12.5

## Container and Runtime Rules

1. Keep service separation intact.
- backend: PHP-FPM API runtime
- frontend: Node/Vite runtime
- db: MySQL
- nginx: reverse proxy entrypoint
- cron: PHP-CLI scheduled tasks

2. Frontend access policy.
- Use nginx as the main local entrypoint.
- Route "/" to frontend and "/api/" to backend.

3. Database host port.
- Local .env currently maps DB_PORT to 3307 to avoid port conflicts.
- Do not force 3306 if host conflict exists.

4. Docker workflow.
- Validate with: docker compose config
- Build with: docker compose build
- Start with: docker compose up -d
- Check with: docker compose ps

## Backend (Symfony) Development Guidelines

Follow Symfony best practices:
- Keep controllers thin and free of business logic.
- Use dependency injection and constructor injection.
- Use Doctrine entities and repositories properly.
- Use migrations for schema changes.
- Prefer attributes where appropriate.
- Keep application code in src/ and config in config/.
- Write tests with PHPUnit (functional tests for HTTP behavior first).

## Frontend (React + Vite) Guidelines

- Keep the frontend simple and incremental in early phases.
- Use React functional components.
- Keep routing and API integration explicit and easy to follow.
- Avoid unnecessary library additions before requirements demand them.

## API and Integration Conventions

- Backend endpoints should be under /api.
- Use JSON for request/response payloads.
- Return clear HTTP status codes and predictable response shapes.
- Keep API contracts stable once consumed by frontend.

## Cron and Scheduled Tasks

- Use the cron container only for scheduled/background CLI commands.
- Register schedules in docker/cron/crontab.
- Keep jobs idempotent and safe to retry.

## Development Process Expectations

- Implement in small, verifiable steps.
- Keep documentation current as architecture evolves.
- Favor clarity and maintainability over premature abstraction.
- Avoid changing locked versions unless explicitly requested.

## Current Baseline Status

- Docker stack boots successfully.
- Nginx routes to frontend and API.
- Backend currently has bootstrap response and is ready for full Symfony initialization.
- Frontend baseline is running with React and Vite.

## Next Preferred Step

Initialize full Symfony 7.4 application structure in backend and add first appointment-related API endpoints, then connect frontend flows incrementally.
