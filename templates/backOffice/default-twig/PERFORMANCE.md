# Performance considerations — back-office Twig template

## Pre-rendered modals — current state

Several screens (`LangController::buildListContext`, `CurrencyController`, `OrderStatusController`, `CustomerTitleController`, …) render N form views inline on the list screen — one create modal plus one update modal per row. The pattern is fine for the System domain volumes (≤ 25 entities) but becomes a problem on the catalog side.

### Why

Each modal is generated server-side, even when the user never opens it. The cost is dominated by :

- `FormFactory::createNamed()` per row (allocates the FormBuilder + FormConfig + Twig template variables).
- A full `form->createView()` walk (resolving choice options, default values, attr merges).

On `admin/configuration/languages` with 11 languages, that is 11 form views per render. Negligible.

On `admin/products` with 5 000 products and a modal per row, we would render 5 000 form views — multi-second TTFB.

### Catalog list screens already mitigate this

The product, category and brand list screens use a single shared "delete confirmation" modal that is pre-filled at click time by the `bo-prefill-modal` Stimulus controller (look for `data-controller="bo-prefill-modal"` in `templates/backOffice/default-twig/`). The DataTable component itself renders **one** modal, no matter how many rows.

This means the cost is currently bounded :

- 1 create modal (rendered once on the list screen, hidden until the user clicks "Add").
- 1 delete modal (idem).
- 0 update modal (the edit screen is a full page, not a modal).

So **no list screen suffers from the N-modal trap today**. The risk surface is :

1. New screens that ship per-row pre-rendered modals (avoid).
2. The configuration domain screens that DO ship per-row update modals (LangController, CurrencyController, …) — fine for the System volumes but **not** to be ported as-is to the catalog domain.

## Recommended pattern for large lists

When a screen handles > 200 entities AND needs an update modal :

1. Render the form once with empty defaults.
2. On row click, populate via JS (`bo-prefill-modal` controller).
3. Or, lazy-load the form via HTMX `hx-get` against a dedicated route returning the form HTML fragment.

## Coupon condition / type pickers — large catalog ceiling

The four advanced coupon condition pickers (`cart-contains-products`, `cart-contains-categories`, `countries`, `customers`) and the legacy on-list pickers (`_categories_picker.html.twig`, `_products_picker.html.twig`, `free-product.html.twig`) eagerly load **every** matching row via `ProductQuery::create()->find()` / `CategoryQuery::create()->find()`. The choices are rendered into a `<select multiple>` plus a client-side filter (`bo-coupon-multiselect-search`).

Comfortable up to ~500 rows. Above that, the HTML payload of the form grows noticeably (each option ≈ 60 bytes plus the JSON product-by-category map for the cascading picker) and the client filter starts feeling sluggish.

`for_some_customers` already trims the list to "50 latest customers + the ones already selected on this coupon".

**Next-step recommendation** (deferred): expose a dedicated AJAX search endpoint per resource (`/admin/coupon/condition/products/search?q=…`) and rewrite the picker as a Stimulus typeahead. Selected ids stay in a hidden `<input>` collection. Fine for any catalog size, no large initial payload.

## Other levers (deferred to F.1 Lighthouse session)

- Bundle size : `webpack-bundle-analyzer` audit (current bundle ≈ 715 KiB after S8 additions).
- Bootstrap Icons tree-shaking : only import the icon classes actually used (we currently ship the full 2 094-icon CSS, ≈ 50 KiB).
- LiveComponent lazy loading on dashboard widgets.
- Asset preload / fetch-priority on the form theme CSS.
- Cache strategy : Symfony page-cache for read-only screens.

## Monitoring

When the user opens a catalog screen and TTFB > 500 ms, capture :

```bash
# from the container
ddev exec bin/console debug:event-dispatcher kernel.request
```

…and check the Symfony profiler `_profiler` panel `Twig` for the per-template render time. Modal pre-renders show as duplicated `@BackOfficeDefaultTwig/*` entries.
