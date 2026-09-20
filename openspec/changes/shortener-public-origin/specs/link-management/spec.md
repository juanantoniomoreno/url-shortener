# Link Management Delta

## ADDED Requirements

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

---

## Review notes (non-normative — not requirement content)

The quoted text below is review context only. It MUST NOT be carried into `openspec/specs/**` when the deltas in this file are composed into the canonical spec.

Quoted canonical text extended by "Build the short URL from the configured shortener origin" (canonical `openspec/specs/link-management/spec.md`, "Requirement: Create short links"):

> "The response MUST include the link's `slug`, original `url`, public `shortUrl`, `clicks`, `createdAt`, `updatedAt`, and `isExpired` fields. Newly created links MUST have zero clicks and MUST NOT be expired."

The current canonical spec only says "public `shortUrl`"; it does not state that the URL is absolute, where it comes from, or that it resolves. This delta adds that contract.
