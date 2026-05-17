# thelia/backoffice-default-template

Smarty-based back-office template for Thelia 3 — historically named "default".
This package ships both the Smarty templates (`*.html`, `*.tpl`, hooks, assets) and
the PHP back-office bundle that hosts the legacy admin controllers, forms, routing
and form registry.

## What this package provides

```
templates/backOffice/default/
├── BackOfficeDefaultBundle.php           Symfony bundle entry point
├── Routing/                              Custom route loader (#[Route] scanning)
├── DependencyInjection/Compiler/         CompilerPass merging admin forms
├── Controller/Admin/                     46 legacy admin controllers (Thelia\Controller\Admin\*)
├── Form/                                 100+ admin forms (Thelia\Form\*)
├── Config/Resources/
│   ├── routing/admin.xml                 334 legacy /admin/* routes
│   └── parameters/forms_admin.php        119 admin form registry entries
├── *.html, *.tpl, admin-layout.tpl, …    Smarty templates
├── assets/, components/, I18n/, …
```

## Why these PHP classes live here

Before this extraction (cf. PLAN.md), the admin controllers and forms lived in
`core/lib/Thelia/Controller/Admin/` and `core/lib/Thelia/Form/`. Moving them
to the back-office template package decouples the core from the legacy Smarty
admin layer while preserving full compatibility with third-party modules
(the namespaces `Thelia\Controller\Admin\*` and `Thelia\Form\*` are kept
intact via Composer PSR-4 path mapping; cf. `AUDIT_RESULTS.md` for the impact
audit on the top-30 third-party modules).

## Activation

The bundle activates automatically when:

1. The package is installed (`composer require thelia/backoffice-default-template`).
2. The bundle is registered in `config/bundles.php` (handled by the Flex recipe
   in `thelia/thelia-recipes`).
3. The application's `config/routes.yaml` imports the bundle's attribute routes:
   ```yaml
   bo_default_admin_attributes:
       resource: .
       type: bo_default_attribute
   ```
   This entry is also installed by the Flex recipe.

The admin form registry (`Thelia.parser.forms`) is merged with the bundle's
admin entries **only when `active-admin-template` equals `default`** (DB config).
This means third-party admin templates (e.g. `default-twig`) can coexist
without registry collision.

## Compatibility

- The `Thelia\Form\BaseForm`, `FormInterface`, `EmptyForm`, `Exception\`,
  `Definition\AdminForm`, `Definition\FrontForm`, `Image\` and all front forms
  remain in the core, since 87 % of third-party modules inherit `BaseForm` and
  the Selection module inherits `Form\Image\ImageModification`.
- The `Thelia\Controller\Admin\BaseAdminController`, `AbstractCrudController`,
  `AbstractSeoCrudController`, `AdminController` remain in the core for the
  same reason (~80 % of modules inherit from one of them, and `AdminController`
  is inherited by BetterSeo, ColissimoLabel, SEOne).

## Maintenance

- Adding or removing an admin form: update `Config/Resources/parameters/forms_admin.php`.
- Adding or removing an admin route: update `Config/Resources/routing/admin.xml`
  or use `#[Route]` on the controller and let
  `BackOfficeDefaultAttributeLoader` pick it up.
- Adding or removing an admin controller: drop the file in `Controller/Admin/`
  (PSR-4 will pick it up via `Thelia\Controller\Admin\*` mapping).

PHPStan and php-cs-fixer scan this directory like any other PHP source.
