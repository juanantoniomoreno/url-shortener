import { useCallback, useEffect, useRef, useState } from "react";
import { createLink, fetchLinks } from "./api.js";

const DEFAULT_STATUS = { kind: "idle", message: "" };

function LinkItem({ link }) {
	const statusLabel = link.isExpired ? "expired" : "active";
	return (
		<li className="link-item">
			<a href={link.shortUrl}>{link.shortUrl}</a>
			<span className="link-url">{link.url}</span>
			<span className="link-clicks">clicks: {link.clicks}</span>
			<span className={`link-status link-status-${statusLabel}`}>{statusLabel}</span>
		</li>
	);
}

function LinkForm({ onSubmit }) {
	const [url, setUrl] = useState("");
	const [slug, setSlug] = useState("");
	const [submitting, setSubmitting] = useState(false);

	async function handleSubmit(event) {
		event.preventDefault();
		setSubmitting(true);
		try {
			await onSubmit(url, slug);
			setUrl("");
			setSlug("");
		} finally {
			setSubmitting(false);
		}
	}

	return (
		<form onSubmit={handleSubmit}>
			<div>
				<label htmlFor="original-url">Original URL</label>
				<input
					id="original-url"
					type="text"
					value={url}
					onChange={(event) => setUrl(event.target.value)}
					required
				/>
			</div>
			<div>
				<label htmlFor="custom-slug">Custom slug (optional)</label>
				<input
					id="custom-slug"
					type="text"
					value={slug}
					onChange={(event) => setSlug(event.target.value)}
				/>
			</div>
			<button type="submit" disabled={submitting}>
				Shorten
			</button>
		</form>
	);
}

function App() {
	const [links, setLinks] = useState([]);
	const [status, setStatus] = useState(DEFAULT_STATUS);
	// In-flight GET responses must not clobber a link that was created while
	// the request was still loading, so every list write invalidates older loads.
	const loadSequence = useRef(0);

	const refresh = useCallback(async () => {
		const sequence = ++loadSequence.current;
		setStatus({ kind: "loading", message: "Loading links…" });
		try {
			const data = await fetchLinks();
			if (sequence !== loadSequence.current) {
				return;
			}
			setLinks(data);
			setStatus(DEFAULT_STATUS);
		} catch (error) {
			if (sequence !== loadSequence.current) {
				return;
			}
			setStatus({ kind: "error", message: error.message });
		}
	}, []);

	useEffect(() => {
		refresh();
	}, [refresh]);

	async function handleCreate(url, slug) {
		try {
			const created = await createLink(url, slug);
			loadSequence.current += 1;
			setLinks((current) => [created, ...current]);
			setStatus(DEFAULT_STATUS);
		} catch (error) {
			setStatus({ kind: "error", message: error.message });
		}
	}

	return (
		<div style={{ maxWidth: 600, margin: "0 auto", padding: 20 }}>
			<h1>URL SHORTENER</h1>

			<LinkForm onSubmit={handleCreate} />

			{status.kind === "loading" && <p>{status.message}</p>}
			{status.kind === "error" && (
				<p role="alert" className="error-message">
					{status.message}
				</p>
			)}

			{/* The empty state is mutually exclusive with loading and error
			    messages so only one list-state message renders at a time. */}
			{links.length === 0 && status.kind === "idle" && (
				<p>No links yet. Create one above.</p>
			)}
			{links.length > 0 && (
				<ul className="link-list">
					{links.map((link) => (
						<LinkItem key={link.slug} link={link} />
					))}
				</ul>
			)}
		</div>
	);
}

export default App;
