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
