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

## Right-to-left

`specs/rtl.spec.ts` walks the buying journey (home, category, product, cart) in
Arabic and replays it in French. It checks the `dir` attribute, the absence of a
horizontal scroll at 1440px and at 390px, that the header flips to the other
side, and that prices and the store phone number are still painted left to right
inside right-to-left text. The reading order is measured character by character
from the boxes the browser paints, so removing a `dir="ltr"` or a `<bdi>` from
the theme fails the test.

It needs an active Arabic language on the shop (`ar` / `ar_SA`) and reads the
category and product URLs off the home page — a language without rewritten URLs
serves `?view=…` links where French serves slugs.

```bash
npx playwright test specs/rtl.spec.ts
```

## Accessibility

`specs/accessibility.spec.ts` runs [axe](https://github.com/dequelabs/axe-core)
with the WCAG 2.1 A and AA rules (the base of RGAA 4.1) on the sign-in and
sign-up forms, on every page listed in `setup/audit/lighthouserc.default.yaml`
(signed in) and on each step of the checkout tunnel up to the confirmation. It
fails on any serious or critical violation that is not a derogation listed in
`helpers/a11y.ts`. Each page logs how many rules axe really checked: a page
where nothing applied fails, it does not pass. The Symfony debug toolbar is left
out of the audit.

It also checks that the first tab stop is a skip link that moves the focus to
`#main-content`, and that no element sticks out of a 320px viewport (RGAA 10.11).
That last check reads the element boxes, not the document scroll width: Flexy
clips the body horizontally, so an overflow never shows as a scrollbar.

```bash
npx playwright test specs/accessibility.spec.ts
```

The `accessibility` workflow runs it on every pull request against a demo shop
served by `php -S`. Outside DDEV, point the SQL helpers at the shop database:

```bash
BASE_URL=http://127.0.0.1:8000 MYSQL_COMMAND="mysql -h127.0.0.1 -uroot -proot thelia" \
  npx playwright test specs/accessibility.spec.ts
```

## Reports

After a run, open `playwright-report/index.html` (`npm run report`).
Failures keep traces, screenshots, and videos under `test-results/`.
