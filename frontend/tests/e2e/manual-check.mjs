import { chromium } from "@playwright/test";

const DASHBOARD = "http://localhost:3000";

const browser = await chromium.launch();
const page = await browser.newPage();

// Step 1: expired marker check
await page.goto(DASHBOARD);
const expItem = page.getByRole("listitem").filter({ hasText: "https://example.com/manual/expired-check" });
await expItem.waitFor();
const expText = await expItem.textContent();
console.log("EXPIRED_ITEM_TEXT:", expText?.replace(/\s+/g, " ").trim());
if (!/expired/i.test(expText ?? "")) throw new Error("expired marker missing");
if (/active/.test(expText ?? "")) throw new Error("expired link shown as active");
console.log("EXPIRED_MARKER_OK");

const clickItem = page.getByRole("listitem").filter({ hasText: "https://example.com/manual/click-check" });
await clickItem.waitFor();
const beforeText = await clickItem.textContent();
console.log("CLICK_ITEM_BEFORE:", beforeText?.replace(/\s+/g, " ").trim());
if (!/clicks: 0/.test(beforeText ?? "")) throw new Error("clicks not 0 before redirect");
if (!/active/i.test(beforeText ?? "")) throw new Error("active link not shown as active");

// Step 2: follow the redirect in the browser (302 to the original URL)
const response = await page.goto("http://localhost:8080/manualclick1");
console.log("REDIRECT_STATUS:", response.status(), "URL:", page.url());

// Step 3: poll the dashboard for the async click update
await page.goto(DASHBOARD);
await clickItem.waitFor();
await page
	.waitForFunction(
		() => document.body.innerText.includes("clicks: 1") || document.body.innerText.includes("clicks: 1 "),
		{ timeout: 30000 },
	)
	.catch(() => {
		throw new Error("click count did not update asynchronously within 30s");
	});
const afterText = await clickItem.textContent();
console.log("CLICK_ITEM_AFTER:", afterText?.replace(/\s+/g, " ").trim());
console.log("ASYNC_CLICK_UPDATE_OK");

await browser.close();
