/**
 * JSON-aware wrappers for the link API.
 *
 * Every non-2xx response is surfaced as an Error carrying the backend
 * `error.code` / `error.message` contract plus the HTTP status.
 */

async function toApiError(response) {
	let code = "request_failed";
	let message = `Request failed with HTTP status ${response.status}.`;
	try {
		const data = await response.json();
		if (data && data.error && data.error.code) {
			code = data.error.code;
			message = data.error.message;
		}
	} catch {
		// Non-JSON error body: keep the generic message.
	}
	const error = new Error(message);
	error.code = code;
	error.status = response.status;
	return error;
}

export async function fetchLinks() {
	const response = await fetch("/api/links");
	if (!response.ok) {
		throw await toApiError(response);
	}
	return response.json();
}

export async function createLink(url, slug) {
	const payload = slug ? { url, slug } : { url };
	const response = await fetch("/api/links", {
		method: "POST",
		headers: { "Content-Type": "application/json" },
		body: JSON.stringify(payload),
	});
	if (!response.ok) {
		throw await toApiError(response);
	}
	return response.json();
}
