# Thelia 3 — Playwright E2E

End-to-end tests for the Flexy front-office and the back-office.

## Setup

```bash
cd tests/Playwright
npm install
npx playwright install chromium
```

## Run

```bash
# All specs (headless)
npm test

# UI mode (interactive)
npm run test:ui

# A single spec
npx playwright test specs/auth.spec.ts

# Override base URL
BASE_URL=https://thelia-3.ddev.site npm test
```

The DDEV stack must be up and the demo data installed (`bin/install --with-demo`).
A demo coupon `E2E10` is created on demand by `helpers/coupon.ts`.

## Layout

- `specs/` — test files (one per flow)
- `helpers/` — page actions and fixtures (register, login, cart, checkout, admin, backoffice crawler)
- `fixtures/` — custom Playwright fixtures (authed customer, products, coupons)

## Backoffice smoke

`specs/backoffice-smoke.spec.ts` is a regression net for the admin: it
authenticates, crawls every reachable admin page (GET), then re-submits the
first non-destructive POST form found on each edit page. Any 5xx response
fails the suite with a one-liner per offending URL.

It catches the typical post-Symfony-7 regressions (`?int` Propel setters
receiving form strings, `Form::isValid()` called before `isSubmitted()`,
`Request::query` / `Request::request` typed as `InputBag`, …).

```bash
# Default scope (300 GET, 100 POST)
npx playwright test specs/backoffice-smoke.spec.ts

# Deep crawl (closer to a nightly job)
BACKOFFICE_MAX_PAGES=1500 BACKOFFICE_MAX_FORMS=400 \
  npx playwright test specs/backoffice-smoke.spec.ts

# Custom admin credentials
ADMIN_USER=alice ADMIN_PASSWORD=secret \
  npx playwright test specs/backoffice-smoke.spec.ts
```

The crawl skips destructive routes (`delete`, `toggle`, `logout`, position
moves, exports, password resets, …) and only re-posts forms whose `action`
also looks safe. Forms are re-submitted with their **own** initial values, so
the test does not need to invent fixture data and does not mutate the demo
dataset in surprising ways.

## Reports

After a run, open `playwright-report/index.html` (`npm run report`).
Failures keep traces, screenshots, and videos under `test-results/`.
