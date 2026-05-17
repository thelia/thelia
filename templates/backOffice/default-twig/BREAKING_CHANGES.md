# BREAKING CHANGES — Back-office Twig (`default-twig`)

> Document destiné aux **mainteneurs de modules tiers Thelia 3**.
> Lire attentivement avant d'activer `--backoffice_theme=default-twig`.

## Vue d'ensemble

Le nouveau back-office `default-twig` est un **template séparé** (Composer package `thelia/backoffice-default-twig-template`) qui cohabite avec le template Smarty historique `default`. Les deux peuvent coexister en runtime via `bin/install --backoffice_theme=default-twig` ou `--backoffice_theme=default`.

**Politique de rétrocompatibilité** :

- **Iso URL strict** : aucune URL `/admin/*` ne change, aucun nom de route ne change.
- **Iso ACL strict** : les constantes `AdminResources::*` sont inchangées. Un voter Symfony custom (`BackOfficeDefaultTwigBundle\Security\AdminVoter`) traduit `is_granted('UPDATE', 'admin.product')` vers `SecurityService::assertAuth` legacy.
- **Iso noms de hooks strict** : aucun nom de hook BO supprimé ou renommé. Nouveaux hooks autorisés (voir §3).
- **BC relâchée côté code BO** : suppression d'`AbstractCrudController`, refactor des forms BO en `final readonly`, etc.

## 1. Cohabitation et activation

### Bascule `default` ↔ `default-twig`

```bash
# Switch vers le BO Twig
ddev exec bash -c "bin/console template:set backOffice default-twig && bin/console cache:clear -e dev"

# Retour BO Smarty legacy
ddev exec bash -c "bin/console template:set backOffice default && bin/console cache:clear -e dev"
```

Mécanique : `BackOfficeDefaultTwigBundle::loadExtension()` early-return si `%thelia_admin_template% !== 'default-twig'`. Si le bundle Twig est actif, `BackOfficeTwigOnlyCompilerPass` peut **optionnellement** désactiver `router.admin` (routes XML legacy) via `strictRoutingOverride = true`. Par défaut **`false`** — cohabitation routing : les routes legacy XML restent actives.

### Sous `default-twig`, quelles routes sont natives vs legacy ?

Au moment de la première release alpha, environ **62%** des routes admin sont natives BO Twig. Les routes restantes sont gérées par les controllers legacy et leurs templates Smarty (cohabitation `router.admin`). Le portage progressif est planifié sur les releases suivantes.

## 2. Changements de patterns côté core BO

### 2.1 Abandon `AbstractCrudController`

Le pattern Template Method héritage (`AbstractCrudController`, ~530 lignes, 13 méthodes abstraites) est **abandonné** côté BO Twig au profit d'une composition par services injectables :

- `AdminAccessChecker` — `check(string $resource, array $modules, string $access): ?Response`
- `AdminFormValidator` — `validate(FormInterface $form): FormInterface`
- `AdminLogger` — `log(string $resource, string $access, string $message, ?int $objectId): void`
- `AdminFormErrorRenderer` — `setup(string $title, string $message, ?FormInterface $form, ?\Throwable $e): void`
- `AdminFormAction` orchestrateur — `submit()` (form-driven) et `tokenAction()` (CSRF token-driven)

**Impact module tiers** : un module qui hérite encore `AbstractCrudController` legacy continue à fonctionner sous `default-twig` (cohabitation `router.admin`). Migration recommandée vers `final readonly class` controller avec services Admin injectés.

### 2.2 Forms BO refactorés

Les forms BO Twig sont `final class extends AbstractType` avec injection constructeur Symfony moderne. Les **block prefixes legacy sont maintenus** (par exemple `thelia_brand_creation`, `thelia_customer_create`, `thelia_product_modification`) → les listeners modules tiers `FORM_BEFORE_BUILD` / `FORM_AFTER_BUILD` continuent à fonctionner.

**Diff** :

- Plus de `BaseForm::init()` à 6 arguments (factory Thelia 2 legacy abandonnée pour les forms BO Twig).
- Plus de `getName(): string` static (le block prefix est géré via FQCN + options Symfony).

**Modules tiers qui ré-instancient `BrandCreationForm` ou autres forms BO directement** doivent migrer vers les types BO Twig (namespace `BackOfficeDefaultTwigBundle\Form\*`). Sous `default-twig`, les forms legacy XML continuent à fonctionner via le routing cohabitation.

### 2.3 ACL : `{loop type=auth}` → `is_granted()` natif Symfony

Le BO Twig utilise `is_granted('UPDATE', 'admin.product')` natif Symfony via le voter custom `AdminVoter`. Iso ACL backend (délègue à `SecurityService::assertAuth` Thelia).

**Impact module tiers Twig** : utiliser `is_granted` standard.
**Impact module tiers Smarty** : `{loop type=auth}` continue à fonctionner via le BO Smarty (cohabitation). Un module qui rend du HTML dans un hook BO Twig peut :

- Continuer à utiliser ses templates Smarty rendus via `ParserResolver` (cross-parser) — toujours fonctionnel.
- Migrer vers Twig avec `{% if is_granted(...) %}`.

## 3. Hooks

### 3.1 Iso noms strict

Aucun nom de hook BO n'est supprimé ou renommé. L'ensemble des hooks référencés dans le BO Smarty existant est conservé dans le BO Twig (déclencheurs `{{ hook(name, params) }}`).

### 3.2 Nouvelles fonctions Twig

Côté BO Twig, trois fonctions complémentaires à `hook()` natif `TwigEngine` :

- `{{ hook_block(name, params) }}` — équivalent Smarty `{hookblock}` (itère les fragments retournés par les listeners `HookRenderBlockEvent`).
- `{{ has_hook(name, params) }}` — `bool` si au moins un listener actif (équivalent Smarty `{ifhook}` / `{elsehook}`).
- `{{ safe_hook(name, params) }}` — wrap `hook()` avec try/catch tolérant les hooks Smarty-only qui crashent en cross-parser.

**Impact module tiers** : ces 3 fonctions sont **additives**. Les modules peuvent les utiliser dans leurs templates Twig de hook. Les modules qui ne rendent que du Smarty continuent à fonctionner sans changement.

### 3.3 Bridge cross-parser

Un listener qui rend du Smarty (`{loop}`, `{hook}`, `{form}`, etc.) via `ParserResolver` continue à fonctionner même rendu dans un layout BO Twig. `ParserResolver::getParser()` itère les parsers taggés `thelia.parser.template` et choisit selon `supportTemplateRender()`. L'ordre Twig > Smarty (priorité 100) est imposé par `BackOfficeTwigOnlyCompilerPass::prioritizeTwigParser()` quand le BO Twig est actif.

## 4. Changements d'URL / routes

**Aucun** changement d'URL côté admin. Aucun nom de route legacy n'est renommé. Les routes XML legacy ont leurs équivalents 1:1 en `#[Route]` attribute PHP côté BO Twig.

**Incohérences héritées préservées** pour ne pas casser les bookmarks et intégrations existantes :

- `admin.categories.set-default` est en réalité un toggle visibility (nom legacy trompeur).
- `admin.products.set-default` est un toggle visibility produit.
- `mailingSystem` en camelCase, `product_sale_elements` en snake_case — conservés.

Aucun module tiers qui fait `path('admin.foo.bar')` n'est cassé.

## 5. Asset pipeline

**Stack BO Twig** : Webpack Encore (cohérence avec le front Flexy), Bootstrap 5.3, Stimulus, HTMX, Bootstrap Icons.

**Diff vs `default` Smarty** :

- Suppression complète de jQuery et des 14 libs vendor jQuery (`bootstrap-select`, `bootstrap-datetimepicker`, etc.).
- Bootstrap 3 → 5.3 (`panel` → `card`, `glyphicon-*` → `bi-*`).
- Less → SCSS.

**Impact module tiers qui injecte du JS via un hook BO** :

- Module injectant du JS qui dépend de jQuery sur le BO Twig → **CASSÉ**. Le module doit embarquer sa propre copie de jQuery (`<script src="jquery.min.js">`) ou migrer.
- Module injectant du JS vanilla → OK.
- Module injectant des classes Bootstrap 3 (`panel`, `well`, `glyphicon-*`) → visuellement cassé sur le BO Twig. Le mapping `bootstrap-classes` documente les remplacements (voir l'asset pipeline du bundle).

**Recommandation** : tester votre module sur `--backoffice_theme=default-twig`. Si l'UI casse, embarquer les dépendances jQuery / Bootstrap 3 ou migrer aux équivalents Bootstrap 5.

## 6. Suppression du BO Smarty `default`

La suppression du BO Smarty `default` est planifiée sous quatre conditions :

1. 100% des écrans BO migrés et testés en Twig.
2. Tous les `thelia-modules/*` activables testés sur le BO Twig.
3. Tous les `local/modules/*` du projet de référence (`with-demo`) testés.
4. Décision communautaire (vote contributeurs Thelia).

Tant que ces conditions ne sont pas remplies, la cohabitation reste activée et le BO Smarty est conservé.

## 7. Patterns de migration recommandés

### 7.1 Pour un controller héritant `AbstractCrudController`

**Avant** (legacy) :

```php
class MyController extends AbstractCrudController
{
    public function __construct()
    {
        parent::__construct('my-resource', /* ... */, AdminResources::MY, /* events */);
    }
    protected function getCreationForm(): BaseForm { /* ... */ }
    protected function getCreationEvent(array $formData): ActionEvent { /* ... */ }
    // 13 méthodes abstraites + hooks
}
```

**Après** (BO Twig idiomatique) :

```php
#[Route('/admin/my-resource', name: 'admin.my-resource.')]
final class MyController
{
    public function __construct(
        private readonly AdminFormAction $action,
        private readonly AdminAccessChecker $access,
        // 5 services injectés
    ) {}

    #[Route('', name: 'default', methods: ['GET'])]
    public function list(): Response { /* ... */ }

    #[Route('/create', name: 'create', methods: ['POST'])]
    public function create(): Response
    {
        $form = $this->formFactory->createNamed('my_resource_creation', MyResourceType::class);

        return $this->action->submit(/* ... */);
    }
    // Une méthode par action, ≤ 30 lignes, orchestration only
}
```

### 7.2 Pour un form héritant `BaseForm`

**Avant** :

```php
class MyForm extends BaseForm
{
    protected function buildForm(): void { /* via $this->formBuilder */ }

    public static function getName(): string
    {
        return 'my_form';
    }
}
```

**Après** :

```php
final class MyResourceType extends AbstractType
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {}

    public function buildForm(FormBuilderInterface $builder, array $options): void { /* ... */ }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['csrf_token_id' => 'admin.my-resource']);
    }
}
```

Le block prefix sera auto-généré depuis le FQCN (`my_resource`). Pour conserver l'iso block prefix, créer le form via `formFactory->createNamed('my_form', MyResourceType::class)`.

### 7.3 Pour un module rendant du HTML dans un hook BO

**Avant** : template Smarty `Module/templates/backOffice/default/my-hook.html` avec `{loop}`, `{hook}`, Bootstrap 3.

**Après** (compatible cohabitation, sans migration immédiate) :

- Le template Smarty continue à fonctionner via `ParserResolver`.
- Veiller à ne pas dépendre de jQuery / Bootstrap 3 spécifiques (classes / plugins).

**Migration progressive** (recommandée pour les futures versions du module) :

- Créer `Module/templates/backOffice/default-twig/my-hook.html.twig`.
- Le `ParserResolver` choisira selon `active-admin-template`.
- Utiliser les classes Bootstrap 5 + `is_granted()` + `safe_hook()`.

## 8. Comment tester son module sur le BO Twig

```bash
# 1. Installer Thelia avec le BO Twig
ddev exec php bin/install --backoffice_theme=default-twig --with-demo --with-admin \
  --admin_login=thelia --admin_password=thelia \
  --admin_first_name=thelia --admin_last_name=thelia \
  --admin_email=thelia@example.com

# 2. Installer et activer son module
ddev exec composer require my-vendor/my-module
ddev exec bin/console module:activate MyModule

# 3. Tester les écrans BO impactés par le module
# Vérifier le rendering des hooks (HTML injecté dans les pages BO Twig)
# Vérifier que les listeners FORM_BEFORE_BUILD / FORM_AFTER_BUILD attachent leurs champs

# 4. Si le module rend des templates :
#   - Smarty templates → automatique via ParserResolver
#   - Twig templates → préférer .html.twig
```

## 9. Reporting d'issues

Si votre module casse sous le BO Twig :

- Ouvrir une issue sur le repo du module (mainteneur tiers) si la cassure vient du module.
- Ou ouvrir une issue sur `thelia/thelia` avec le tag `bo-twig:module-compat`, le nom du module et les étapes de reproduction.

## 10. Cas connus détectés (cohabitation, baseline 2026-05-17)

Premier passage de validation contre les modules tiers shippés dans `vendor/thelia/modules/`. Détails complets et workarounds dans `MODULE_COMPATIBILITY.md`.

### 10.1 Module `CustomerFamily` — 500 sur `/admin/module/CustomerFamily`

**Symptôme** : `Unable to load template 'file:admin-layout.tpl' in 'file:customer_family_module_configuration.html'`.

**Cause** : le template Smarty `customer_family_module_configuration.html` étend `admin-layout.tpl`. Ce layout ne fait pas partie du package Twig, et le ParserResolver TwigParser/SmartyParser ne le résout pas depuis le tree BO Twig.

**Workaround temporaire** : `bin/console template:set backOffice default` pendant la session de configuration du module, puis re-bascule. Une fois la flip stricte (E.4) effective, le mainteneur devra fournir une vue Twig.

### 10.2 Hook `wysiwyg.js` non appelé sur les pages d'édition BO Twig

**Symptôme** : Tinymce, CKEditor, etc. ne remplacent pas les `<textarea>` du back-office.

**Cause** : les templates Twig (`product/update.html.twig`, `category/edit.html.twig`, …) ne contiennent pas encore l'appel `{{ safe_hook('wysiwyg.js') }}` (ou équivalent) qui permettrait à Tinymce de pousser ses scripts.

**Statut** : à standardiser dans une itération Phase E suivante. Les contenus se saisissent toujours via les textareas natives.

### 10.3 Modules ne shippant pas dans `vendor/thelia/modules`

`MailingTemplate` et `NewsletterCustomConfig` ne sont pas installés par défaut dans `thelia/thelia`. Tests reportés à la phase E suivante quand ils seront ajoutés via `composer require`.

### 10.4 Modules avec hooks back-office spécifiques

Tout hook utilisant `{loop}` ou `{intl}` natifs Smarty dans son fragment doit être testé individuellement. La cohabitation TwigParser/SmartyParser fonctionne correctement pour `{hook}` / `{hookblock}` / `{forhook}` standards (cf §3).

Le développement actif du BO Twig **ne** modifie pas les modules tiers (politique BC relâchée côté BO core uniquement).
