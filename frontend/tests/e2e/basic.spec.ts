import { test, expect } from "@playwright/test";

test("page loads and displays title", async ({ page }) => {
	await page.goto("/");

	await expect(page.locator("h1")).toHaveText("URL SHORTENER");
	await expect(page.locator("p")).toContainText("Scaffolding ready");
});
