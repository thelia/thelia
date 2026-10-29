# Updating Thelia

This guide covers updating an existing Thelia 3 site to a newer Thelia 3 release.

**Coming from Thelia 2?** Thelia 3 cannot update a Thelia 2 database in place. The
updater refuses any database below `3.0.0`, and moving a 2.x shop to 3.0 is a guided
migration, not an in-place update. Follow <https://doc.thelia.net/docs/upgrading/migrate>.

## Before you start

Back up your files and your database. `mysqldump` is enough for the database:

```bash
mysqldump -u <user> -p <database> > backup.sql
```

## 1. Update the code

Thelia 3 is a Composer package, so you update the code with Composer. From the root
of your project:

```bash
composer update thelia/thelia-skeleton --with-all-dependencies
```

Check the release notes for the version you move to: a module or a template you depend
on may need its own bump in `composer.json`.

## 2. Update the database

Run the update script from the root of your project:

```bash
php local/setup/update.php
```

When the database is not on the version of the code, it first removes the compiled
container and the generated Propel models of the previous release (`var/cache/<env>` and
`var/propel/<env>`), so the new schema is read, then reports the version it starts from and
the one it moves to, and applies each database migration in order. It offers to back the
database up first; on a large database, take the manual backup above instead. If a
migration fails, the script stops and offers to restore that backup.

Those two directories are often written by the web server user. When the script cannot
delete some of their files, it moves the directory aside (`var/cache/<env>.previous-…`),
where nothing loads it, prints the command that deletes it as its owner, and goes on. When
it cannot even move the directory, it stops with code `8` before touching the database and
prints that command: run it, or run the script as the web server user, then start again.

In a deployment pipeline, pass `--no-interaction` (or `-n`, or `--yes`): every question is
answered yes, the backup and the restore after a failure included. The script exits with
`0` when the update succeeds and when the database is already on the version of the code,
so it can run on every release; any other code is a failure.

## 3. Rebuild the cache

The update script has already removed the compiled cache and the Propel runtime of the
environment it ran in. Do not run `cache:clear` in production: it empties the cache without
rebuilding it, and the first request then compiles it under load. Warm the cache back up
instead:

```bash
php Thelia cache:warmup --env=prod
```

If the script ran in another environment than the one you serve, remove that one first:
`rm -rf var/cache/prod var/propel/prod`.

## 4. Rebuild the theme assets

In production the themes serve compiled assets. Once the cache is warm, compile them again:

```bash
php Thelia importmap:install --env=prod
php Thelia asset-map:compile --env=prod
```

`asset-map:compile` builds the Flexy stylesheet minified and the back-office stylesheet
compressed, then writes a `.gz` copy of each text asset next to it, plus `.br` and `.zst`
copies when the `brotli` and `zstd` commands (or the PHP extensions of the same names) are
installed on the machine that runs it. Apache sends these copies through `public/.htaccess`;
nginx sends them once configured as the nginx page of the documentation shows.

The compile builds the Flexy stylesheet with the Tailwind binary, which it downloads into
`var/tailwind/` when it is missing: the machine that compiles needs to reach GitHub once, or
to have the binary already there.

An update does not replace the `public/.htaccess` of a shop. To serve the compressed copies
with Apache, copy into yours the rules of the `public/.htaccess` of this release (the block
about precompressed assets, and the `<IfModule mod_headers.c>` block after it). Remove the
`BrotliCompressionQuality` line if your file has one: Apache refuses it in a `.htaccess` and
answers 500 to every request when `mod_brotli` is loaded.

## Converting a database migrated from Thelia 2 to utf8mb4

Thelia 2 created its tables in `utf8` (`utf8mb3`) and the migration keeps them in it. Thelia 3
connects in `utf8mb4`, and a fresh install creates its tables in `utf8mb4`. On a migrated shop
the database refuses an emoji, or any other character outside the Basic Multilingual Plane,
with error 1366 ("Incorrect string value"), while a fresh install stores it.

The update script does not convert these tables. The conversion rebuilds each table and locks
it for writes while it runs, for a time that grows with its size, so you run it yourself, once,
during a maintenance window. List what would change first:

```bash
php bin/console thelia:database:convert-utf8mb4
```

The command lists each table that is not in `utf8mb4` with its columns and approximate size.
It flags the tables it moves from the `COMPACT` to the `DYNAMIC` row format: in `COMPACT`,
InnoDB caps an index at 767 bytes, a `VARCHAR(255)` in `utf8mb4` needs 1020, and the fresh
install creates its tables in `DYNAMIC`. It also names what it cannot convert on its own and
converts nothing until that is settled: a text column used by a foreign key, or an index longer
than the engine accepts. A table in another character set, such as `latin1`, is reported and
left alone, because its bytes may be UTF-8 written through a `latin1` connection.

Back the database up, then convert:

```bash
php bin/console thelia:database:convert-utf8mb4 --force
```

Each table moves to `utf8mb4` with `utf8mb4_general_ci`, the collation of the fresh install,
and its `TEXT` columns stay `TEXT`. The database default follows, so a module installed later
creates its tables in `utf8mb4`. If a table fails, the command stops there and prints the
database error. The tables converted before it stay converted, and the next run picks up the
rest. `--table=<name>`, repeated, converts only the named tables, which lets you spread the
largest ones over several windows.

## Release notes

### Moving from 3.2.x to 3.3.0

Follow the steps above: `composer update`, `php local/setup/update.php`, then `php Thelia module:refresh`. The sections below cover the AI layer; the other changes are in `CHANGELOG.md`.

#### AI layer: tables, dependencies and recipes

`php local/setup/update.php` runs `setup/update/sql/3.3.0.sql`, which creates nine tables
(`ai_connection`, `ai_model`, `ai_tier_assignment`, `ai_usage_setting`, `ai_usage_log`,
`ai_budget_reservation`, `ai_tool_token`, `ai_tool_approval`, `ai_tool_call_log`), the rights
`admin.configuration.ai` and `admin.configuration.ai-mcp`, and the settings `ai_enabled`,
`ai_monthly_cap`, `ai_eu_only`, `ai_log_retention_days`, `ai_mcp_enabled`,
`ai_tool_approval_ttl_hours` and `ai_tool_token_max_days`. A setting the shop already holds is left alone.

The core now requires `symfony/ai-platform` and the bridges `symfony/ai-anthropic-platform`,
`symfony/ai-generic-platform`, `symfony/ai-mistral-platform`, `symfony/ai-ollama-platform` and
`symfony/ai-open-ai-platform` (all `^0.15`), `symfony/mcp-bundle` (`^0.15`) and `nyholm/psr7`
(`^1.8`). A project that pins any of them must allow these versions.

Composer may apply the Symfony recipes of these packages and write files such as
`config/packages/ai_*_platform.yaml` (an `ai:` key) and `config/packages/mcp.yaml`. They do no harm:
the core accepts and ignores the `ai:` key, since the shop reads its connections from the database and
`symfony/ai-bundle` is not installed, and it replaces the `default` MCP server of the recipe by its own.
You can delete these files.

#### The AI layer stays off until you set it up

A shop updated to 3.3.0 calls no model. Nothing happens until an administrator adds a connection in
the AI settings and a module declares a usage that needs it. The back-office screens come with the
`default-twig` theme: update it together with the core. The MCP server on `/api/mcp` is off by default
(`ai_mcp_enabled` is `0`); an administrator switches it on in the AI settings and creates the tokens.
If the shop is served on a host other than the one in `url_site`, set `THELIA_MCP_ALLOWED_HOSTS` to the
comma-separated host names the endpoint must accept.

### Moving from 3.1.x to 3.2.0

#### What the update script does now

`php local/setup/update.php` changed in 3.2:

- It accepts `-n` or `--no-interaction`.
- It exits with code 0 when the database is already up to date.
- It purges `var/cache/<env>` and `var/propel/<env>` itself before it starts.

```bash
php local/setup/update.php --no-interaction
```

The script also performs these changes on the database:

- It copies the image file into the translation of each active language, then drops the
  `file` column of the image tables (see "Image files per language" below).
- It switches `active-admin-template` to `default-twig` and deactivates TheliaSmarty (see
  "The Smarty back-office is gone" below).

#### After the Composer update

Run the module refresh once Composer is done:

```bash
php Thelia module:refresh
```

A module newly shipped with the release is registered inactive. Activate it from the
back-office, or with `php Thelia module:activate <ModuleCode>`.

TheliaCMS (`thelia/cms-module`) is not required by the skeleton. To add it to a project:

```bash
composer require thelia/cms-module
php Thelia module:refresh
```

#### API Platform stays on 4.3

The core pins API Platform to 4.3.x and conflicts with 4.4 on purpose. A project that requires
`^4.4` cannot resolve: set the constraint back to `^4.3` in `composer.json`.

Add one setting to `config/packages/api_platform.yaml`. It removes the "multiple ApiResource
with the same shortName" warnings:

```yaml
# config/packages/api_platform.yaml
api_platform:
    defaults:
        extra_properties:
            deduplicate_resource_short_names: true
```

#### Image files per language

The `file` column of `product_image`, `category_image`, `content_image`, `folder_image` and
`brand_image` is removed. Each image now carries its file in its translation, so an image can
differ from one language to the next. The update script copies the existing file into the
translation of every active language.

A module that read the `file` column has to read `file` from the matching `*_image_i18n` table
instead (`product_image_i18n`, `category_image_i18n`, and so on). Check the modules that
export or display images; for example, GoogleShoppingXml and EasyProductManager need their
4.0.0 versions.

#### The Smarty back-office is gone

The Smarty back-office is removed. The update script sets `active-admin-template` to
`default-twig` and deactivates TheliaSmarty. A shop that still wants the Smarty back-office
adds `thelia/backoffice-default-template` itself; that package is no longer maintained.

#### Back-office CSRF token

The back-office still accepts the CSRF token in the URL, but this is deprecated and 3.3 will
refuse it. A module that builds back-office requests sends the token in the POST body
instead. Toggles, position changes and deletions require the token: a request without a valid
one answers 403.

#### Themes

Flexy and default-twig 1.2 are required: the 3.2 core refuses a theme older than 1.2. Updating
through `thelia/thelia-skeleton` brings them. If you forked Flexy 1.1:

- Take over the `GuestOrderPlacedSubscriber` fix of Flexy 1.2: the core no longer raises
  `ORDER_CART_CLEAR`.
- Your theme can now read the content slots.

#### Payment modules

The payment flow changed: `supportsPaymentRetry()` is a new part of the contract, the cart is
kept until the payment completes, and the amount is checked together with the shipping cost.
Review each payment module of the shop against
Payment modules.

### Moving from 3.0.0 to 3.1.0

Two changes of this release show up in production without anything being asked for.

The API caps a page at one hundred items. A caller asking for more receives one hundred
items and no error, so an integration that walks a catalogue in a single call has to move
to paginated reads. A project that needs another ceiling redefines
`pagination_maximum_items_per_page` in its own `api_platform` configuration:

```yaml
# config/packages/api_platform.yaml
api_platform:
    defaults:
        pagination_maximum_items_per_page: 500
```

The API also limits its rate: two hundred requests a minute for an anonymous caller, eight
hundred for an authenticated customer, two thousand for the administration, ten failed
login attempts and twenty token refreshes. Each ceiling is set by a
`THELIA_API_RATE_LIMIT_*` environment variable, and a list of addresses and CIDR ranges
exempts trusted callers, which is what a payment gateway or a data feed needs.

Three more points to check before you update:

- An updated shop and a fresh install differ on one row: the terms and conditions consent
  is created mandatory on a fresh install, and optional on an updated shop, so that a theme
  which does not render the consent box yet cannot block the checkout. Once the theme shows
  it, switch it to mandatory from the consent configuration screen.

- The connection now names its character set in the DSN, `utf8mb4`, when the DSN named
  none. A DSN written in a `database.yml` is taken as it is, so a shop that picked its own
  keeps it. A database inherited from a Thelia 2 migration whose tables stayed in `latin1`
  has to name its set in the DSN before updating.
- Every response carries `X-Frame-Options: SAMEORIGIN` unless the shop already sets the
  header. A shop displayed in an iframe on another domain has to write its own value.

Update the code with Composer, from the root of your project:

```bash
composer update thelia/core thelia/setup thelia/config thelia/flexy \
  thelia/backoffice-default-twig-template thelia/email-default-template \
  thelia/pdf-default-template --with-all-dependencies
```

`thelia/setup` and `thelia/config` ship as 3.1.1 with this core; the 3.1.0 tags of these two
packages were published early and lack the last tables of the release.

Then update the database, as described in section 2 (the script lives under `local/`
in a project installed from `thelia/thelia-project`):

```bash
php local/setup/update.php
```

The themes follow the core. Flexy 1.1.0, default-twig 1.1.0, email 1.1.0 and pdf 1.1.0 need
a 3.1 core: they render the checkout steps, the consent boxes, the offered cart lines, the
reserved sales and the order returns this release adds. Rebuild the cache as described in
section 3, then rebuild the theme assets.
