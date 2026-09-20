# Dashboard Delta

## MODIFIED Requirements

### Requirement: Display expiration status

The dashboard MUST distinguish active and expired links using the API's computed `isExpired` value and MUST display each link's click count and short URL. The displayed short URL MUST be the API-returned `shortUrl` — an absolute URL at the configured shortener origin (`SHORTENER_BASE_URL`) that resolves through the redirect endpoint — rather than a URL derived from the dashboard's own origin or the proxied request host.

#### Scenario: Show an expired link as inactive

- GIVEN the list API marks a link as expired
- WHEN the dashboard renders the list
- THEN it labels the link as expired or inactive
- AND does not present it as an active redirect

#### Scenario: Displayed short URL points at the shortener origin

- GIVEN the API is reached through any proxy or host
- WHEN the dashboard renders a link's short URL
- THEN the displayed URL is the API-returned absolute `shortUrl` at the configured shortener origin
- AND following it resolves through the redirect endpoint instead of the dashboard SPA

---

## Review notes (non-normative — not requirement content)

The quoted text below is review context only. It MUST NOT be carried into `openspec/specs/**` when the deltas in this file are composed into the canonical spec.

Quoted canonical text modified by "Display expiration status" (canonical `openspec/specs/dashboard/spec.md`):

> "The dashboard MUST distinguish active and expired links using the API's computed `isExpired` value and MUST display each link's click count and short URL."
