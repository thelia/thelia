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
composer update thelia/thelia --with-all-dependencies
```

Check the release notes for the version you move to: a module or a template you depend
on may need its own bump in `composer.json`.

## 2. Update the database

Run the update script from the root of your project:

```bash
php setup/update.php
```

It reports the version it starts from and the one it moves to, then applies each
database migration in order. It offers to back the database up first; on a large
database, take the manual backup above instead. If a migration fails, the script stops
and offers to restore that backup.

## 3. Rebuild the cache

Do not run `cache:clear` in production: it empties the cache without rebuilding it, and
the first request then compiles it under load. Remove the compiled cache and the Propel
runtime, then warm the cache back up:

```bash
rm -rf var/cache/prod var/propel/prod
php Thelia cache:warmup --env=prod
```

In development, `var/cache/dev` and `var/propel/dev` are the ones to remove.

## Release notes

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

Then update the database, as described in section 2:

```bash
php setup/update.php
```

The themes follow the core. Flexy 1.1.0, default-twig 1.1.0, email 1.1.0 and pdf 1.1.0 need
a 3.1 core: they render the checkout steps, the consent boxes, the offered cart lines, the
reserved sales and the order returns this release adds. Rebuild the cache as described in
section 3, then rebuild the theme assets.
