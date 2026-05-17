# Module conventions for the back-office Twig template

The `default-twig` back-office hosts third-party module configuration screens through the same mechanism as the legacy `default` Smarty template, plus a new Twig-native convention. Both work side by side during the cohabitation phase.

## URL — `/admin/module/{module_code}`

The legacy convention is preserved : every third-party module that ships an admin configuration screen MUST keep responding to `/admin/module/{module_code}`. The route is registered by `router.admin` in `core/lib/Thelia/Config/Resources/routing/admin.xml`. Concrete dispatching is done by the module itself by overriding `BaseModule::getHooks()` or by registering a controller behind the `admin.module.configure` route name.

Modules that already do this in Thelia 2 work unchanged under `default-twig`. The cohabitation compiler pass keeps `router.admin` registered when `strictRoutingOverride` is false (current default).

## Symfony attribute — `#[Route('/admin/module/<code>')]`

For Thelia 3 native modules, prefer a typed Symfony controller :

```php
namespace MyModule\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/module/MyModule', name: 'mymodule.admin.configure')]
final class ConfigureController
{
    public function __invoke(): Response
    {
        return new Response('<h1>MyModule configuration</h1>');
    }
}
```

The route name `mymodule.admin.configure` is free as long as the URL stays under `/admin/module/<code>`. The standard Thelia kernel discovers attribute-based routes automatically inside enabled modules.

## Layout integration

Configuration screens SHOULD extend the BO Twig base layout to inherit the sidebar, top bar and the form theme :

```twig
{% extends '@BackOfficeDefaultTwig/base.html.twig' %}

{% block title %}{{ 'MyModule configuration'|trans }}{% endblock %}

{% block content %}
    <div class="container-fluid">
        <h1 class="h3 mb-3">{{ 'MyModule'|trans }}</h1>
        <div class="card">
            <div class="card-body">
                {# ... your form / settings ... #}
            </div>
        </div>
    </div>
{% endblock %}
```

The `@BackOfficeDefaultTwig` namespace is registered by the bundle when `default-twig` is the active back-office template.

## Hooks — appending to the side nav

To add an entry under one of the existing sidebar sections, register a hook listener on the matching `main.top-menu-<section>` block hook. Each fragment is rendered as a sub-item under the section :

```php
use Thelia\Core\Event\Hook\HookRenderBlockEvent;
use Thelia\Core\Hook\BaseHook;

final class MyModuleHook extends BaseHook
{
    public static function getSubscribedHooks(): array
    {
        return [
            'main.top-menu-tools' => [['type' => 'back', 'method' => 'onTopMenuTools']],
        ];
    }

    public function onTopMenuTools(HookRenderBlockEvent $event): void
    {
        $event->add([
            'id' => 'mymodule-nav-link',
            'class' => 'mymodule-nav',
            'url' => '/admin/module/MyModule',
            'title' => $this->trans('MyModule'),
        ]);
    }
}
```

Supported sidebar hooks (iso Smarty) :

- `main.top-menu-customer`
- `main.top-menu-order`
- `main.top-menu-catalog`
- `main.top-menu-content`
- `main.top-menu-tools`
- `main.top-menu-modules`
- `main.top-menu-configuration`
- `main.in-top-menu-items`

## ACL

Each configuration screen MUST gate on the appropriate `AdminResources::*` (or a module-specific string) :

```php
if ($denied = $this->access->check('admin.module', [], AccessManager::VIEW)) {
    return $denied;
}
```

## Forms

Forms exposed inside a configuration screen pick up the BO Twig form theme automatically (`bo_form_theme.html.twig`). Use the `form_row` / `form_label` / `form_widget` helpers to get consistent rounded inputs, focus rings and "(optional)" labels rendered on their own line.

## See also

- [`README.md`](README.md) — back-office Twig architecture and CRUD recipe.
- [`BREAKING_CHANGES.md`](BREAKING_CHANGES.md) — migration guide for Thelia 2 modules.
