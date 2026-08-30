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
