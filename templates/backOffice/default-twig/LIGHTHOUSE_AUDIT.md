# Lighthouse + Webpack audit procedure

Run this checklist on every major release of the BO Twig template (typically after a `npm run build` and before tagging a beta/stable). Target scores : LCP < 2.5 s, CLS < 0.1, FID < 100 ms, total bundle < 600 KiB gzipped.

## 1. Lighthouse run

```bash
# from the host
google-chrome --headless --disable-gpu \
  --remote-debugging-port=9222 \
  https://thelia-3.ddev.site/admin &

npx --yes lighthouse https://thelia-3.ddev.site/admin \
  --output=html --output-path=./test-results/lighthouse-home.html \
  --chrome-flags="--ignore-certificate-errors --headless" \
  --preset=desktop \
  --only-categories=performance,accessibility,best-practices
```

Repeat for `/admin/products`, `/admin/customers`, `/admin/configuration`, `/admin/orders` to cover the heaviest views.

## 2. Bundle analyzer

```bash
ddev exec bash -c "cd templates/backOffice/default-twig && npm install --save-dev webpack-bundle-analyzer"
```

Then add to `webpack.config.js` :

```js
.addPlugin(new (require('webpack-bundle-analyzer').BundleAnalyzerPlugin)({
    analyzerMode: 'static',
    reportFilename: 'bundle-report.html',
    openAnalyzer: false,
}))
```

`npm run build` writes `dist/bundle-report.html` — open it to see which chunk is biggest.

## 3. Bootstrap Icons tree-shaking

The full bootstrap-icons CSS ships **all** 2 094 icons. Once the BO Twig template is stable, audit the actual usage :

```bash
grep -rho 'bi bi-[a-z0-9-]*' templates/backOffice/default-twig/ \
  | sort -u > used-icons.txt
wc -l used-icons.txt
```

If the count is significantly below 2 094, replace the wholesale `@import '~bootstrap-icons/font/bootstrap-icons.scss';` with a custom `@font-face` + only the `.bi-foo:before { content: '\fXXX'; }` lines actually referenced.

## 4. Asset preload

Add inside `base.html.twig` `<head>` for the critical assets :

```twig
{% block preload %}
    <link rel="preload" href="{{ dist }}/img/logo-thelia-34px.png" as="image">
    <link rel="preload" href="{{ dist }}/img/svgFlags/{{ bo_current_language().code }}.svg" as="image">
{% endblock %}
```

## 5. LiveComponent lazy loading

Where a `<twig:BoLiveSomething />` block is below the fold, mark it with `loading="lazy"` and a `min-height` placeholder to avoid CLS.

## 6. Compression

Confirm `Content-Encoding: br` (or `gzip`) on the CSS / JS responses :

```bash
curl -k -s -H 'Accept-Encoding: br,gzip' -o /dev/null -w '%{http_code} %{header_json}\n' https://thelia-3.ddev.site/templates-assets/backOffice/default-twig/dist/app.<hash>.css | jq '.["content-encoding"]'
```

## 7. Service worker (optional)

Once the back-office is feature-complete and rarely changes, opt in to a service-worker shell cache for the static asset set. Workbox or a 50-line custom worker.

## Targets

| Metric | Target | Current (2026-05-17, S9 baseline) |
|---|---|---|
| LCP | < 2.5 s | **0.5 s** ✅ on `/admin/login` (anonymous) |
| CLS | < 0.1 | **0** ✅ |
| TBT | < 200 ms | **0 ms** ✅ |
| FCP | < 1.8 s | **0.4 s** ✅ |
| Speed Index | < 3.4 s | **1.0 s** ✅ |
| Bundle (uncompressed) | < 600 KiB | **715 KiB** ⚠️ |
| Lighthouse perf | > 90 | **100** ✅ |

Audit run via `npx lighthouse https://thelia-3.ddev.site/admin/login --preset=desktop`. Authenticated screens (`/admin/products`, `/admin/customers`, …) need a Lighthouse user-flow with login automation to be measured; deferred to session 10 along with the F.1 deep-dive and the strict-routing flip.

## Bootstrap Icons usage audit (2026-05-17)

Quick audit of icons referenced in BO Twig templates :

```bash
grep -rho 'bi bi-[a-z0-9-]*' templates/backOffice/default-twig/ \
  --include="*.twig" --include="*.js" --include="*.scss" \
  --exclude-dir=node_modules --exclude-dir=dist | sort -u | wc -l
# → 71 distinct icon classes in use vs 2 094 shipped
```

71 / 2 094 used (≈ 3.4 %). The full Bootstrap Icons CSS adds ≈ 50 KiB to the bundle. Tree-shaking would shave that down to ≈ 2 KiB. Low-priority optimization since the total bundle still meets the perf budget.

## Recommendations (do not block alpha release)

1. Tree-shake Bootstrap Icons — 50 KiB saved, low effort.
2. Switch the cascading product/category pickers in the coupon editor to an AJAX typeahead — preserves perf on large catalogs (≥ 500 products). See `PERFORMANCE.md`.
3. Re-run Lighthouse with an authenticated user-flow once the BO is in a maintenance window (post-alpha).
