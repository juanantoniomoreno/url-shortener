# Link Management Specification

## Purpose

Provide the public URL-shortening lifecycle: create a short link, resolve it through a redirect, and expose deterministic expiration behavior without authentication.

## Requirements

### Requirement: Create short links

The system MUST accept `POST /api/links` with a JSON object containing a required `url` string and an optional `slug` string, validate the input, persist a link, and return the created link as JSON with HTTP status `201`.

The response MUST include the link's `slug`, original `url`, public `shortUrl`, `clicks`, `createdAt`, `updatedAt`, and `isExpired` fields. Newly created links MUST have zero clicks and MUST NOT be expired.

#### Scenario: Create a link with an automatically generated slug

- GIVEN a valid absolute HTTP or HTTPS URL and no custom slug
- WHEN the client sends `POST /api/links`
- THEN the system persists one link
- AND returns HTTP `201`
- AND returns a seven-character alphanumeric slug
- AND returns a public short URL containing that slug
- AND returns `clicks` equal to `0`

#### Scenario: Create a link with a custom slug

- GIVEN a valid URL and a custom slug containing only letters, numbers, hyphens, or underscores
- WHEN the client sends `POST /api/links`
- THEN the system persists the requested slug unchanged
- AND returns HTTP `201` with that slug

#### Scenario: Reject invalid creation input

- GIVEN a request with a missing URL, a non-HTTP(S) URL, malformed JSON, or an invalid custom slug
- WHEN the client sends `POST /api/links`
- THEN the system returns a client-error response
- AND does not persist a link
- AND returns a JSON error that identifies the validation failure

#### Scenario: Reject a slug collision

- GIVEN a custom slug that already belongs to another link
- WHEN the client sends `POST /api/links`
- THEN the system returns HTTP `409`
- AND does not modify either existing link

### Requirement: Generate unique slugs

The system MUST generate seven-character slugs from the alphanumeric character set when no custom slug is supplied. Generated slugs MUST be checked for uniqueness and MUST NOT use reserved route names such as `api`, `admin`, or `dashboard`.

The generator MUST retry a collision or reserved value no more than ten total attempts and MUST return a clear server error when no usable slug can be produced.

#### Scenario: Retry a generated collision

- GIVEN the first generated candidate is already persisted
- WHEN a new link requests an automatic slug
- THEN the generator tries another candidate
- AND persists only a unique, non-reserved candidate

#### Scenario: Stop after slug generation exhaustion

- GIVEN every candidate in the ten-attempt generation budget collides or is reserved
- WHEN a new link requests an automatic slug
- THEN the system returns a server error
- AND does not persist a partial link

### Requirement: Redirect active links

The system MUST accept `GET /{slug}` for a known, non-expired slug, return HTTP `302`, and set the `Location` header to the original URL.

A successful redirect MUST dispatch one `LinkVisited` message to the asynchronous Messenger transport. The redirect request MUST NOT synchronously increment the click counter.

#### Scenario: Redirect an active link

- GIVEN a persisted link whose last activity is less than thirty days old
- WHEN a client requests `GET /{slug}`
- THEN the system returns HTTP `302`
- AND the `Location` header equals the original URL
- AND one asynchronous visit message is dispatched
- AND the persisted click count is unchanged before the worker handles the message

#### Scenario: Return not found for an unknown slug

- GIVEN no persisted link matches the requested slug
- WHEN a client requests `GET /{slug}`
- THEN the system returns HTTP `404`
- AND no visit message is dispatched

### Requirement: Expire inactive links lazily

The system MUST consider a link expired when its `updatedAt` is older than thirty days relative to the current application clock. The redirect endpoint MUST return HTTP `410` for an expired link and MUST NOT dispatch a visit message.

#### Scenario: Reject an expired link

- GIVEN a persisted link whose `updatedAt` is more than thirty days in the past
- WHEN a client requests `GET /{slug}`
- THEN the system returns HTTP `410`
- AND no visit message is dispatched

#### Scenario: Keep a recently visited link active

- GIVEN a link older than thirty days by creation date
- AND its `updatedAt` was refreshed by a processed visit less than thirty days ago
- WHEN a client requests `GET /{slug}`
- THEN the system returns HTTP `302`
- AND treats the link as active

### Requirement: Build the short URL from the configured shortener origin

The system MUST generate the public `shortUrl` as an absolute URL built from the explicitly configured `SHORTENER_BASE_URL` shortener origin, followed by the link's slug. The generated `shortUrl` MUST be independent of the incoming request's Host header.

#### Scenario: Short URL ignores the request host

- GIVEN `SHORTENER_BASE_URL` is configured as `https://short.test`
- WHEN a client sends `POST /api/links` with a Host header of `evil.example.com:9999`
- THEN the system returns HTTP `201`
- AND returns a `shortUrl` starting with `https://short.test/`
- AND the `shortUrl` does not contain `evil.example.com`

#### Scenario: Short URL is absolute and ends with the slug

- GIVEN a configured shortener origin
- WHEN a link is created with any slug
- THEN the returned `shortUrl` is an absolute HTTP or HTTPS URL
- AND its path ends with `/` followed by the link's slug

### Requirement: Short URL resolves through the redirect endpoint

Following a returned `shortUrl` MUST reach the redirect endpoint: the origin and path of the configured `SHORTENER_BASE_URL` MUST route `GET /{slug}` to the link redirect handler, returning HTTP `302` for an active link or HTTP `410` for an expired one — never the SPA or another non-redirect response.

#### Scenario: Following the short URL of an active link

- GIVEN a persisted, non-expired link
- WHEN a client follows the returned `shortUrl`
- THEN the response is HTTP `302`
- AND the `Location` header equals the original URL

#### Scenario: Following the short URL of an expired link

- GIVEN a persisted link whose last activity is more than thirty days old
- WHEN a client follows the returned `shortUrl`
- THEN the response is HTTP `410`
