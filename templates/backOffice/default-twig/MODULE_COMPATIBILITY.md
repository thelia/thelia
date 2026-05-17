# Third-party module compatibility under `default-twig`

The Twig back-office runs in cohabitation with the Smarty `default` template. Most existing Thelia 2 modules keep working unchanged because :

- The hook system (`{hook}` / `{hookblock}` / `{forhook}`) still dispatches through the same event names (`main.top-menu-<section>`, `home.block`, `product.tab`, …). The Twig template exposes `hook_block()` / `safe_hook()` Twig functions that consume the same fragments.
- ACL resources (`AdminResources::CUSTOMER`, etc.) are unchanged.
- Symfony forms behave identically; the BO Twig form theme is rendered via the standard form theme mechanism.
- The `router.admin` legacy collection is still registered when `strictRoutingOverride = false` (current default), so any module that exposes routes through `config.xml` `<route>` keeps responding.

## Tested modules (priority 1)

The following modules need a confirmed pass under `BO_TEMPLATE=default-twig` before E.4 (strict routing flip) can land. Run each through the smoke checklist :

1. Install the module via Composer or extraction in `local/modules/`.
2. `ddev exec bin/console module:activate <Code>`.
3. Visit `/admin` under the `default-twig` template and verify :
   - No 5xx (smoke crawler `backoffice-smoke.spec.ts` catches the GET set).
   - The module's admin screen renders correctly (sidebar entry visible, form fields, save flow).
   - Hooks injected by the module (sidebar entries, dashboard widgets, `product.tab`, …) appear.

| Module | Status | Notes |
|---|---|---|
| `HeaderHighlights` | ⚠️ partial | Admin update page (`/admin/module/update/{id}`) renders. No native sidebar/dashboard hook detected under BO Twig (legacy `back_office.home-bottom` hook is dispatched, but the Twig dashboard does not render that block yet). |
| `CustomerFamily` | ❌ broken | Module **configuration** screen 500s under BO Twig because `/admin/module/CustomerFamily` renders the legacy Smarty template `customer_family_module_configuration.html` that extends `admin-layout.tpl`. The layout is not part of the Twig template tree so Smarty cannot resolve it. Workaround: switch to `template:set backOffice default` while editing the module config, or ship a Twig-aware configuration view in the module. |
| `Tinymce` | ⚠️ partial | Admin update page renders. The `wysiwyg.js` hook (sole integration point) is **not** called from BO Twig product/category/content edit forms, so the rich text editor does not replace the textareas. Editing still works through the raw textarea. |
| `MailingTemplate` | ⏸️ not available locally | Module is not shipped with `vendor/thelia/modules`. Skipped in the cohabitation smoke. |
| `NewsletterCustomConfig` | ⏸️ not available locally | Module is not shipped with `vendor/thelia/modules`. Skipped in the cohabitation smoke. |

## Known incompatibilities

1. **Legacy module configuration screens that extend `admin-layout.tpl`**: any module shipping a Smarty template under `templates/backOffice/default/<code>_module_configuration.html` whose markup starts with `{extends file="admin-layout.tpl"}` will 500 under BO Twig because the layout file lives only in the Smarty `default` template tree. **Workaround**: maintainers should ship a Twig-aware view, or wrap the module configuration in a standalone form that does not depend on `admin-layout.tpl`. Until then, admins must temporarily switch the back-office template via `bin/console template:set backOffice default` to configure such modules.
2. **`wysiwyg.js` hook is not yet wired into BO Twig product/category/content/folder edit pages**: rich text editors (Tinymce and friends) do not auto-replace the BO Twig textareas. The HTML form still saves cleanly. Tracking item — to be addressed in a later Phase E increment when we standardize on a BO Twig rich-text bridge.
3. **Sidebar / dashboard hook injection** (`back_office.home-bottom`, `main.top-menu-X`, etc.) is supported in the BO Twig top navigation and home component via `safe_hook()`. Modules using `{hookblock}` Smarty fragments need to be tested individually — the AST is iso, but rendering pipelines diverge for raw `{loop}` / `{intl}` constructs.

## Reporting a compatibility issue

If a module breaks under `default-twig`, file an issue with :

- The module name and version.
- The URL that 5xxs or renders incorrectly.
- Browser console errors (Stimulus controller missing, etc.).
- The output of `ddev exec bin/console debug:event-dispatcher kernel.request` (helps spot listener priority conflicts).
