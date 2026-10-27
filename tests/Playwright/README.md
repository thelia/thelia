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

## Customer groups

Seven specs cover the customer-group feature (`specs/customer-groups-*.spec.ts`), one per domain:

| Spec | What it proves |
|------|----------------|
| `customer-groups-prices` | default group taxed without discount; member (15%, untaxed) sees untaxed prices + mention on listing, product page, cart (Total excl. tax, VAT, Total incl. tax to pay); a group price beats the discount; the catalog promotion is not discounted by the group |
| `customer-groups-catalog` | category whitelist: menu, way-through parent, 404 on other categories and on a product filed elsewhere only; the default group sees everything |
| `customer-groups-payment` | payment whitelist: only Cheque, no gift card, no instant parts; the default group keeps them |
| `customer-groups-cart` | a guest cart loses the closed product (with a message) when a restricted member signs in |
| `customer-groups-order` | an order keeps the untaxed display, group and rate after the customer changes group |
| `customer-groups-backoffice` | groups screen, whitelists, emptied-list warning, customer sheet, customer list filter, group prices on the product sheet, registration link |
| `customer-groups-registration` | `/register/{token}`: account created in the group; regenerated, closed or unknown token: 404; standard form: default group |

```bash
# Local DDEV host is https://thelia.ddev.site (the config default is another host): pass BASE_URL
cd tests/Playwright
BASE_URL=https://thelia.ddev.site npx playwright test specs/customer-groups- --reporter=line > ../../var/log/pw-cg.log 2>&1
tail -30 ../../var/log/pw-cg.log

# One domain, one viewport
BASE_URL=https://thelia.ddev.site npx playwright test specs/customer-groups-catalog --project=customer-groups-mobile-390
```

Projects: `customer-groups-desktop-1440` (all the specs) and `customer-groups-mobile-390` (prices and
catalog only). The `chromium` project skips these specs.

On a distribution Playwright does not know (the installer refuses `ubuntu26.04`), install the browser with
`PLAYWRIGHT_HOST_PLATFORM_OVERRIDE=ubuntu24.04-x64 npx playwright install chromium`.

Data: every spec creates its own groups, customers and categories in `beforeAll` / the test itself, through
`helpers/customer-group.ts` (SQL on the DDEV database `db` via `helpers/db.ts`), all named with the `e2e-cg-`
prefix, and removes them in `afterAll` (`purgeCustomerGroupData()`). The demo data and the groups made by
hand (`default`, `pro`) are never written. The suite runs with one worker: the purge removes every
`e2e-cg-` row (and the anonymous carts created since the run started), so two runs must not overlap. After an interrupted run, `purgeCustomerGroupData()` runs again at
the start of the next one.

Helpers: `helpers/customer-group.ts` (data), `helpers/group-pricing.ts` (price and totals readers),
`helpers/checkout.ts` (`goToPaymentStep`, `placeOrderWithCheque`), `poms/backoffice/customer-group-*.ts` and
`product-group-prices-section.ts`.

## Reports

After a run, open `playwright-report/index.html` (`npm run report`).
Failures keep traces, screenshots, and videos under `test-results/`.
