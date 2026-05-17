# Suppression checklist — `templates/backOffice/default/`

Snapshot of the conditions that must be met before we can flip `strictRoutingOverride = true` in `BackOfficeDefaultTwigBundle` and remove the legacy Smarty template.

## Phase A — release blockers

| Item | Status |
|---|---|
| A.1 Bootstrap Icons font glyphs | ✅ baseline OK (2 094 codepoints in compiled CSS, font HTTP 200) |
| A.2 `/admin` home dashboard | ✅ widgets shipped (customers, pending orders, recent orders, low stock) |
| A.3 Duplicate fields on catalog edit forms | ✅ `form_end(form, {render_rest: false})` |
| A.4 Non-active tab color | ✅ neutralized in `_variables.scss` |
| A.5 Action buttons grouping | ✅ `btn-group` on feature edit |

## Phase B — UX

| Item | Status |
|---|---|
| B.1 Sidebar / top nav / footer / lang switcher | ✅ |
| B.2 Playwright cleanup under default-twig | ✅ specs already guarded by `BO_TEMPLATE` |
| B.3 Tax rule matrix UI | ⏳ pending (deferred to session 3) |
| B.4 Customer "Add a new address" modal | ✅ |
| B.5 Category related-content / picture UI | ⏳ pending (session 2) |
| B.6 Feature / Attribute value inline edit | ✅ |
| B.7 Template duplicate name modal | ✅ |
| B.8 Product related tabs UI | ⏳ pending (session 2) |
| B.9 Pricing helpers real calc | ⏳ pending (session 2) |
| B.10 Product attributes / features batch UI | ⏳ pending (session 3) |

## Phase C — Migration of the 126 remaining routes

| Item | Status |
|---|---|
| C.1 Content domain | ✅ 9 routes |
| C.2 Coupon (~10 routes) | 🟡 list + delete only (legacy editor still used for create/update) |
| C.3 Country + State + CustomerTitle (~12 routes) | ✅ 19 routes |
| C.4 Area + Shipping zones + Delivery / Payment modules (~15 routes) | ⏳ pending (session 5) |
| C.5 Newsletter (~5 routes) | ✅ list + delete + CSV export |
| C.6 MailingSystem (SMTP) | ✅ |
| C.6 Mail templates (Message CRUD) | ✅ |
| C.7 Export + Import (~10 routes) | ⏳ pending (session 5) |
| C.8 Hook + Translation + Search + Tools + Password reset (~25 routes) | 🟡 cache-flush already in `AdvancedConfigurationController`; rest pending (session 6) |
| C.9 Address standalone CRUD | ✅ already exposed by `AddressController` |

## Phase D — Advanced features

| Item | Status |
|---|---|
| D.1 Category tree drag&drop multi-level | ⏳ pending (session 7) |
| D.2 Product PSE inline edit + batch save | ⏳ pending (session 7) |
| D.3 Virtual documents UI | ⏳ pending (session 7) |
| D.4 PSE document / image association UI | ⏳ pending (session 7) |
| D.5 PDF invoice / delivery pipeline | ⏳ pending (session 8) |
| D.6 OrderAddressType Symfony Form | ✅ |
| D.7 Module install pipeline | ⏳ pending (session 8) |
| D.8 `module.configure` URL convention | ✅ documented in MODULE_CONVENTIONS.md |
| D.9 Module hook AJAX introspection | ✅ |
| D.10 Image + Document AJAX endpoints (~16 routes) | ⏳ pending (session 8) |

## Phase E — Compatibility + flip

| Item | Status |
|---|---|
| E.1 Third-party modules compatibility tests (5 key modules) | ⏳ pending (session 9) |
| E.2 Pre-rendered modal perf review | ✅ documented in `PERFORMANCE.md` |
| E.3 Final audit (this document) | 🟡 in progress |
| E.4 Flip `strictRoutingOverride = true` | ⛔ blocked until all C/D items land |
| E.5 Removal of `templates/backOffice/default/` | ⛔ blocked until E.4 + community vote |

## Phase F — Polish

| Item | Status |
|---|---|
| F.1 Lighthouse + Webpack analyzer | ⏳ pending (session 10) |
| F.2 README | ✅ shipped |

## Hard blockers before E.4 / E.5

1. **126 legacy routes** → only ~92 remain to port (28 %). Every remaining route must either be ported to Twig or be intentionally dropped from the BO surface.
2. **No 5xx on smoke crawl** under `BO_TEMPLATE=default-twig`. The current `backoffice-smoke.spec.ts` already enforces this for the GET set; we need to add a coverage for the POST forms once all routes land.
3. **Third-party module compatibility** : at least 5 modules (HeaderHighlights, CustomerFamily, TinyMce, MailingTemplate, NewsletterCustomConfig) must install and operate under default-twig.
4. **Community vote** on the cut-over date (out of scope for this branch).

## Soft blockers

- Translation coverage : the FR catalog has the back-office strings; other locales (DE, ES, IT, RU, CS, PT…) need a pass on the new strings introduced by the Twig template (`bo.default-twig` domain).
- Form theme polish : edge cases on `ChoiceType` with multiple, `FileType` placeholder ("No file chosen") and `CollectionType` are not yet covered by `bo_form_theme.html.twig`.

## Verdict (as of 2026-05-16)

The Twig back-office is **production-ready for the 5 main domains** (catalog, customer, order, folder, system). Removing the Smarty template requires finishing 9 more sessions (≈80 h of work) and clearing the soft blockers.

The branch `feat/backoffice-twig` can be **merged progressively** to keep both back-offices in cohabitation, with no risk to the Smarty path.
