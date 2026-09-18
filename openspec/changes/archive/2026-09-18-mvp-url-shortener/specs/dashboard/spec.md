# Dashboard Specification

## Purpose

Give users a minimal unauthenticated dashboard for creating short links and inspecting their current click and expiration state.

## Requirements

### Requirement: List links for the dashboard

The system MUST expose `GET /api/links` and return HTTP `200` with a JSON collection of all links ordered by creation time descending.

Each item MUST include the same public fields as link creation responses: `slug`, original `url`, `shortUrl`, `clicks`, `createdAt`, `updatedAt`, and computed `isExpired`.

#### Scenario: List links with current analytics

- GIVEN multiple persisted links with different click counts
- WHEN the dashboard requests `GET /api/links`
- THEN the system returns HTTP `200`
- AND returns every link ordered newest first
- AND each item includes its current click count and expiration state

#### Scenario: List an empty collection

- GIVEN no links have been persisted
- WHEN the dashboard requests `GET /api/links`
- THEN the system returns HTTP `200`
- AND returns an empty collection rather than an error

### Requirement: Create links from the dashboard

The React dashboard MUST provide a form that submits an original URL and optional custom slug to `POST /api/links`, displays validation or conflict errors, and adds a successful response to the visible list.

#### Scenario: Create a link successfully from the UI

- GIVEN the dashboard is loaded
- WHEN the user submits a valid URL
- THEN the UI sends the creation request
- AND displays the returned short URL in the list
- AND displays its initial click count as zero

#### Scenario: Display a creation error

- GIVEN the API rejects the submitted URL or slug
- WHEN the user submits the form
- THEN the UI remains usable
- AND displays a human-readable error
- AND does not add an invalid item to the list

### Requirement: Display expiration status

The dashboard MUST distinguish active and expired links using the API's computed `isExpired` value and MUST display each link's click count and short URL.

#### Scenario: Show an expired link as inactive

- GIVEN the list API marks a link as expired
- WHEN the dashboard renders the list
- THEN it labels the link as expired or inactive
- AND does not present it as an active redirect

### Requirement: Verify the primary user flow

The project MUST provide an end-to-end test covering dashboard load, link creation, and presentation of the created short link. Backend tests MUST cover the API and asynchronous click-tracking contracts independently from the browser test.

#### Scenario: Complete the create-and-display flow

- GIVEN the application stack and test dependencies are available
- WHEN Playwright loads the dashboard and submits a valid URL
- THEN the API request succeeds
- AND the created short link appears in the dashboard
