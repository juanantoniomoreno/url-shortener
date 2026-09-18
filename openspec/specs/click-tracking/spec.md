# Click Tracking Specification

## Purpose

Record redirect visits asynchronously so redirect latency is independent from the click-counter database write.

## Requirements

### Requirement: Dispatch visit events asynchronously

The system MUST define a `LinkVisited` message containing the resolved link identity and route that message to the `async` Messenger transport backed by RabbitMQ.

The redirect flow MUST dispatch the message only after resolving an active link and MUST leave the redirect response independent from worker completion.

#### Scenario: Queue a visit after an active redirect

- GIVEN an active link exists for the requested slug
- WHEN the redirect controller resolves the link
- THEN it returns the redirect response without waiting for a database counter update
- AND it dispatches one `LinkVisited` message to the `async` transport

#### Scenario: Do not queue rejected visits

- GIVEN the requested slug is unknown or expired
- WHEN the redirect controller handles the request
- THEN it returns `404` or `410` respectively
- AND it does not dispatch a `LinkVisited` message

#### Scenario: Keep redirects available when the broker is unavailable

- GIVEN an active link resolves successfully
- AND publishing to the asynchronous transport fails
- WHEN the redirect controller handles the request
- THEN it still returns HTTP `302` with the original URL
- AND it logs the publishing failure for diagnosis
- AND it does not synchronously update the click counter

### Requirement: Process visit events

The system MUST provide a Messenger handler for `LinkVisited` that loads the referenced link, increments its click counter by one, refreshes `updatedAt` to the current application time, and persists the changes.

The handler MUST be safe when the referenced link no longer exists: it MUST acknowledge the message without creating a new link or causing an unhandled application failure.

#### Scenario: Increment a link from a consumed message

- GIVEN a persisted link with click count `n`
- WHEN the worker consumes a valid `LinkVisited` message for that link
- THEN the handler persists click count `n + 1`
- AND refreshes `updatedAt`

#### Scenario: Ignore a visit for a deleted link

- GIVEN a `LinkVisited` message references a link that cannot be found
- WHEN the worker handles the message
- THEN it does not create or modify any link
- AND it completes without an unhandled exception

### Requirement: Run a dedicated worker

The deployment configuration MUST provide a dedicated `worker` service that reuses the PHP application image, waits for PostgreSQL and RabbitMQ health, and consumes the `async` transport with bounded time and memory limits.

#### Scenario: Start the worker with the application stack

- GIVEN the Docker Compose stack is started
- WHEN PostgreSQL and RabbitMQ become healthy
- THEN the worker service starts the Messenger consumer for `async`
- AND the PHP-FPM service continues serving HTTP independently

#### Scenario: Preserve migration ownership

- GIVEN the worker service starts with a non-FPM command
- WHEN its entrypoint runs
- THEN it does not run database migrations
- AND only the main PHP-FPM service owns startup migrations
