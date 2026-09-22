# URL Shortener

URL shortener with async analytics, using the same stack as **event-driven-orders** but simpler:

| Layer         | Technology                    |
| ------------- | ----------------------------- |
| Backend       | Symfony 7 + Messenger (AMQP)  |
| Frontend      | React 18 + Vite               |
| Queue         | RabbitMQ (AMQP)               |
| Database      | PostgreSQL 16 + Doctrine ORM  |
| Containers    | Docker + Docker Compose       |
| CI/CD         | GitHub Actions                |

## How to Run

```bash
cd backend && composer install && cd ..
cd frontend && npm install && cd ..
docker compose up -d --build
```

## Services

| Service   | Port       | Purpose                    |
| --------- | ---------- | -------------------------- |
| nginx     | 8080       | Reverse proxy to PHP-FPM   |
| php       | —          | Symfony application        |
| postgres  | 5433       | Persistent storage         |
| rabbitmq  | 5673/15673 | Message broker + management UI |
| frontend  | 3000       | React SPA via Nginx        |

## Environment Variables

| Variable                                              | Default (dev stack)                                                                      | Purpose                                                           |
| ----------------------------------------------------- | ---------------------------------------------------------------------------------------- | ----------------------------------------------------------------- |
| `SHORTENER_BASE_URL`                                  | `http://localhost:8080`                                                                  | Public origin used to compose every short URL returned by the API |
| `DATABASE_URL`                                        | `postgresql://shortener:shortener@postgres:5432/shortener?serverVersion=16&charset=utf8` | Doctrine/PostgreSQL connection                                    |
| `MESSENGER_TRANSPORT_DSN`                             | `amqp://guest:guest@rabbitmq:5672/%2f/async`                                             | AMQP transport for the `async` queue consumed by the worker       |
| `APP_ENV`                                             | `dev`                                                                                    | Symfony environment                                               |
| `DEFAULT_URI`                                         | `http://localhost:8080`                                                                  | Request context for non-HTTP contexts (worker, CLI) only          |
| `POSTGRES_USER` / `POSTGRES_PASSWORD` / `POSTGRES_DB` | `shortener`                                                                              | Credentials and database consumed by the `postgres` service       |
| `RABBITMQ_USER` / `RABBITMQ_PASS`                     | `guest`                                                                                  | Credentials consumed by the `rabbitmq` service                    |

`backend/.env` and `backend/.env.example` hold the development defaults, and `docker-compose.yml` passes these variables to the `php` and `worker` services; because `.env` is excluded from the image (`backend/.dockerignore`), a deployment must set real environment variables.

`SHORTENER_BASE_URL` is required in deployment and must be an absolute `http` or `https` URL with a non-empty host (a path is allowed, a query string or fragment is not); the API composes `shortUrl` as `rtrim(base, '/') . '/' . slug`. A malformed value fails the first request that serializes a short URL with an HTTP 500 naming `SHORTENER_BASE_URL`, while the redirect flow (`<origin>/<slug>`) keeps working because it does not depend on the variable. Each short URL is built from the value in effect when it was returned, so changing the variable affects only newly returned short URLs.
