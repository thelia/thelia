# Contributing to Thelia

Thanks for helping improve Thelia. This guide covers how to send a pull request and the checks it needs to pass.

## Where to contribute

Pull requests for the framework go to [thelia/thelia](https://github.com/thelia/thelia). Modules live under [thelia-modules](https://github.com/thelia-modules); open the pull request on the module's own repository. To report a bug or suggest a feature, open an issue.

## Working on a change

Fork the repository, then create a branch for your work:

```bash
git checkout -b fix/short-description
```

Use a descriptive name prefixed by the type of change (`feat/`, `fix/`, `chore/`, `docs/`, `refactor/`, `test/`). The [README](Readme.md) explains how to set up a local environment and install Thelia.

## Coding standard

Thelia follows PSR-12. The project ships a php-cs-fixer configuration, so you do not have to apply the rules by hand:

```bash
ddev exec composer cs        # fix the code style
ddev exec composer cs-diff   # check without changing files
```

Write code that reads like the code around it. Avoid abbreviations in names, and keep PHPDoc only where the types are not already clear from the signature.

## Checks before opening a pull request

Run the checks and make sure they are green:

```bash
ddev exec composer cs-diff    # coding standard
ddev exec composer phpstan    # static analysis
ddev exec composer test       # PHPUnit suites: unit, integration, api, http-flexy, http-backoffice
```

`composer ci` runs the three in order. The continuous integration workflow runs them again on your pull request.

Write commit messages in English, in the imperative, with a type prefix, for example `fix: prevent double tax on free shipping`. Keep one concern per commit.

## Database and schema changes

The SQL files in `setup/` are generated, not edited by hand. If you change reference or demo data, edit `setup/insert.sql.tpl` and regenerate the SQL:

```bash
ddev exec php Thelia generate:sql
```

If you change a Propel schema, regenerate the model classes and SQL as described in the database section of the [documentation](https://doc.thelia.net). For a module, `php Thelia module:generate:model` and `php Thelia module:generate:sql` rebuild its model and schema. Do not commit the generated `setup/insert.sql`, `setup/thelia.sql` or Propel base classes without the matching source change.

## Translations

Keep user-facing strings in English in your pull request. Translations into other languages are coordinated by the maintainers before each release. To help translate Thelia, reach out through the community channels below.

## Community

- Documentation: <https://doc.thelia.net>
- Discord: <https://discord.gg/YgwpYEE3y3>
- Forum: <https://forum.thelia.net/>
