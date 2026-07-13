# AGENTS.md — URL Shortener

## Project Overview

**URL Shortener** — a URL shortening service with async analytics using event-driven architecture.

## Tech Stack

| Layer         | Technology                    |
| ------------- | ----------------------------- |
| Backend       | Symfony 7 + Messenger (AMQP)  |
| Frontend      | React 18 + Vite (minimal)     |
| Queue         | RabbitMQ (AMQP)               |
| Database      | PostgreSQL 16 + Doctrine ORM  |
| Containers    | Docker + Docker Compose       |
| CI/CD         | GitHub Actions                |
| Language      | PHP 8.2 / JavaScript (JSX)    |

## Directory Map

| Path                  | Scope                        |
| --------------------- | ---------------------------- |
| `backend/`            | Symfony API + workers        |
| `frontend/`           | React SPA (minimal)          |
| `.github/workflows/`  | CI pipelines                 |
| `docker-compose.yml`  | Infrastructure (5 services)  |

## Docker Services

| Service    | Port       | Purpose                     |
| ---------- | ---------- | --------------------------- |
| `nginx`    | 8080       | Reverse proxy to PHP-FPM    |
| `php`      | —          | Symfony application          |
| `postgres` | 5433       | Persistent storage          |
| `rabbitmq` | 5673/15673 | Message broker + management UI |
| `frontend` | 3000       | React SPA via Nginx         |

## Key Differences from event-driven-orders

- No Mercure (may add later)
- Single `async` transport in Messenger
- No separate worker services (yet — to be added in SDD)
- No Redis
- Simpler docker-compose (5 services)

## Conventions

- PHP strict types everywhere: `declare(strict_types=1);`
- Namespace: `App\` maps to `src/`
- Constructor injection with PHP 8 promoted properties
- Routing: YAML-based (`config/routes.yaml`)
- DI config: `config/services.yaml` — autowire + autoconfigure
