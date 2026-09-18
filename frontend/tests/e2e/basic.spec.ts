import { test, expect, Page } from "@playwright/test";

type LinkFixture = {
	slug: string;
	url: string;
	shortUrl: string;
	clicks: number;
	createdAt: string;
	updatedAt: string;
	isExpired: boolean;
};

function linkFixture(overrides: Partial<LinkFixture> = {}): LinkFixture {
	const now = new Date().toISOString();
	return {
		slug: "aB3xYz9",
		url: "https://example.com/article",
		shortUrl: "http://localhost:8080/aB3xYz9",
		clicks: 0,
		createdAt: now,
		updatedAt: now,
		isExpired: false,
		...overrides,
	};
}

async function mockGetLinks(page: Page, links: LinkFixture[], status = 200) {
	await page.route("**/api/links", async (route) => {
		if (route.request().method() !== "GET") {
			return route.fallback();
		}
		await route.fulfill({ status, contentType: "application/json", body: JSON.stringify(links) });
	});
}

async function mockPostLink(page: Page, status: number, body: unknown) {
	await page.route("**/api/links", async (route) => {
		if (route.request().method() !== "POST") {
			return route.fallback();
		}
		await route.fulfill({ status, contentType: "application/json", body: JSON.stringify(body) });
	});
}

test.describe("dashboard list rendering (request fixtures)", () => {
	test("dashboard loads, lists links with click counts and active status", async ({ page }) => {
		await mockGetLinks(page, [
			linkFixture({
				slug: "newest1",
				url: "https://example.com/newest",
				shortUrl: "http://localhost:8080/newest1",
				clicks: 4,
			}),
			linkFixture({
				slug: "older22",
				url: "https://example.com/older",
				shortUrl: "http://localhost:8080/older22",
				clicks: 7,
			}),
		]);

		await page.goto("/");

		await expect(page.locator("h1")).toHaveText("URL SHORTENER");

		const newest = page.getByRole("listitem").filter({ hasText: "https://example.com/newest" });
		await expect(newest).toHaveCount(1);
		await expect(newest.first()).toContainText("4");
		await expect(newest.first()).toContainText("active", { ignoreCase: true });

		const older = page.getByRole("listitem").filter({ hasText: "https://example.com/older" });
		await expect(older).toHaveCount(1);
		await expect(older.first()).toContainText("7");

		const shortLink = page.getByRole("link", { name: "http://localhost:8080/newest1" });
		await expect(shortLink).toHaveCount(1);
		await expect(shortLink).toHaveAttribute("href", "http://localhost:8080/newest1");
	});

	test("dashboard shows an empty state when no links exist", async ({ page }) => {
		await mockGetLinks(page, []);

		await page.goto("/");

		await expect(page.getByText(/no links yet/i)).toBeVisible();
		await expect(page.getByRole("listitem")).toHaveCount(0);
	});

	test("dashboard shows a loading state before the list arrives", async ({ page }) => {
		await page.route("**/api/links", async (route) => {
			await new Promise((resolve) => setTimeout(resolve, 400));
			await route.fulfill({
				status: 200,
				contentType: "application/json",
				body: JSON.stringify([linkFixture()]),
			});
		});

		await page.goto("/");
		await expect(page.getByText(/loading/i)).toBeVisible();
		await expect(page.getByRole("listitem")).toHaveCount(1);
	});

	test("dashboard labels an expired link as expired", async ({ page }) => {
		await mockGetLinks(page, [
			linkFixture({
				slug: "oldlink1",
				shortUrl: "http://localhost:8080/oldlink1",
				isExpired: true,
				clicks: 2,
			}),
		]);

		await page.goto("/");

		const item = page.getByRole("listitem").filter({ hasText: "oldlink1" });
		await expect(item).toContainText("expired", { ignoreCase: true });
		await expect(item).not.toContainText("active", { ignoreCase: true });
	});

	test("dashboard surfaces a load failure without crashing", async ({ page }) => {
		await mockGetLinks(page, [], 500);

		await page.goto("/");

		await expect(page.getByRole("alert")).toBeVisible();
	});
});

test.describe("create-link flow (request fixtures)", () => {
	test("successful creation prepends the created link with zero clicks", async ({ page }) => {
		const created = linkFixture({
			slug: "fresh12",
			url: "https://example.com/fresh",
			shortUrl: "http://localhost:8080/fresh12",
		});
		await mockGetLinks(page, []);
		await mockPostLink(page, 201, created);

		await page.goto("/");

		await page.getByLabel(/original url/i).fill("https://example.com/fresh");
		await page.getByLabel(/custom slug/i).fill("fresh12");
		await page.getByRole("button", { name: /shorten/i }).click();

		const item = page.getByRole("listitem").filter({ hasText: "https://example.com/fresh" });
		await expect(item).toHaveCount(1);
		await expect(item).toContainText("0");
		await expect(page.getByRole("link", { name: "http://localhost:8080/fresh12" })).toHaveAttribute(
			"href",
			"http://localhost:8080/fresh12",
		);
	});

	test("failed creation shows a human-readable error and keeps the list unchanged", async ({
		page,
	}) => {
		await mockGetLinks(page, [linkFixture({ slug: "existing", url: "https://example.com/keep" })]);
		await mockPostLink(page, 409, {
			error: { code: "slug_conflict", message: "The requested slug is already in use." },
		});

		await page.goto("/");

		// count() is an imperative snapshot with no auto-waiting, so the baseline must be
		// captured only after the initial load has provably settled.
		const seeded = page
			.getByRole("listitem")
			.filter({ hasText: "https://example.com/keep" });
		await expect(seeded).toHaveCount(1);
		const itemCount = await page.getByRole("listitem").count();
		await page.getByLabel(/original url/i).fill("https://example.com/conflict");
		await page.getByLabel(/custom slug/i).fill("existing");
		await page.getByRole("button", { name: /shorten/i }).click();

		await expect(page.getByRole("alert")).toContainText("The requested slug is already in use.");
		await expect(page.getByRole("listitem")).toHaveCount(itemCount);
	});
});

test.describe("primary user flow (real stack)", () => {
	const stamp = Date.now().toString(36);
	const slug = `e2e-${stamp}`;

	test("creates a link and displays its short URL with zero clicks", async ({ page }) => {
		await page.goto("/");
		await expect(page.getByText(/loading/i)).toBeHidden();

		await page.getByLabel(/original url/i).fill(`https://example.com/e2e/${stamp}`);
		await page.getByLabel(/custom slug/i).fill(slug);
		await page.getByRole("button", { name: /shorten/i }).click();

		const item = page.getByRole("listitem").filter({ hasText: `https://example.com/e2e/${stamp}` });
		await expect(item).toHaveCount(1);
		await expect(item).toContainText(slug);
		await expect(item).toContainText("0");
		const shortLink = page.getByRole("link", { name: new RegExp(slug) });
		await expect(shortLink).toHaveCount(1);
		// shortUrl host depends on the serving proxy; the UI must render the
		// API-returned shortUrl as a link to the created slug.
		await expect(shortLink).toHaveAttribute("href", new RegExp(`/${slug}$`));
		await expect(item).toContainText("active", { ignoreCase: true });
	});

	test("displays a validation error for an invalid URL without adding a list item", async ({
		page,
	}) => {
		await page.goto("/");

		// Reading the rendered list right after navigation races the mount-time GET
		// (count() does not auto-wait). The list API supplies the authoritative
		// baseline and toHaveCount() waits for the render to settle.
		const apiLinks = await (await page.request.get("/api/links")).json();
		await expect(page.getByRole("listitem")).toHaveCount(apiLinks.length);

		await page.getByLabel(/original url/i).fill("not-a-url");
		await page.getByRole("button", { name: /shorten/i }).click();

		await expect(page.getByRole("alert")).toContainText(
			"The URL must be an absolute HTTP or HTTPS URL.",
		);
		await expect(page.getByRole("listitem")).toHaveCount(apiLinks.length);
		await expect(page.getByRole("listitem").filter({ hasText: "not-a-url" })).toHaveCount(0);
	});
});
