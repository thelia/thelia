# Thelia

This is the development repository of Thelia, the open source e-commerce framework. Work on Thelia itself here.

To create a shop, use the project skeleton instead: [thelia/thelia-project](https://github.com/thelia/thelia-project).

## About

Thelia is an open source framework for building online stores and managing web content. Version 3 runs on:

- PHP 8.3
- Symfony 7.4 LTS
- API Platform 4.3 (standalone)
- Propel ORM
- A Twig front office (the Flexy theme) and a Twig back office (the default-twig theme)
- Lexik JWT for API authentication

The back office and front office are built with Twig, Symfony UX (Stimulus, Twig Components and Live Components) and Bootstrap 5. The Smarty back office from Thelia 2 is still available for projects that need it while they migrate. See "Back-office templates" below.

Thelia is open source software. See the [LICENSE](LICENSE) file for details.

## Requirements

- PHP 8.3 with these extensions: pdo_mysql, openssl, intl, gd, curl, dom, mbstring, zip
- MariaDB 10.11 or MySQL 8
- Composer 2.7+
- Node.js 20 and npm, to build the front-office and back-office assets
- Nginx or Apache, with the document root set to `public/`

## Setting up a development environment

The repository does not ship a Docker setup. The maintainers use [DDEV](https://ddev.com), which provides PHP 8.3, MariaDB 10.11 and Node.js 20 in one command. Any equivalent stack works: point your web server at `public/` and use the same PHP 8.3 binary for the command line and the web server.

```bash
ddev config --project-type=php --php-version=8.3 --database=mariadb:10.11 --docroot=public --nodejs-version=20
ddev start
ddev exec composer install
```

### Install Thelia

`bin/install` reads its database credentials from the `DATABASE_HOST`, `DATABASE_PORT`, `DATABASE_NAME`, `DATABASE_USER` and `DATABASE_PASSWORD` environment variables, or from command-line flags. With DDEV the database is reachable as `db:3306`, user `db`, password `db`:

```bash
ddev exec bin/install \
  --frontoffice_theme=flexy --backoffice_theme=default-twig \
  --pdf_theme=default --email_theme=default \
  --with-demo --with-admin \
  --admin_login=thelia --admin_password=thelia \
  --admin_first_name=Admin --admin_last_name=User \
  --admin_email=admin@example.com
```

Without DDEV, export the database variables (or put them in `.env.local`) and run `php bin/install` with the same flags.

### Build the assets

`bin/install` sets up the database and templates but does not compile front-end assets. Build them for each active template that has a `package.json`, otherwise the pages return HTTP 500 with a missing Webpack entrypoints file:

```bash
ddev exec bash -c "cd templates/frontOffice/flexy && npm install && npm run build"
ddev exec bash -c "cd templates/backOffice/default-twig && npm install && npm run build"
```

The storefront is then at `https://<project>.ddev.site` and the admin at `https://<project>.ddev.site/admin`.

## Back-office templates

Thelia 3 installs the Twig back office (`default-twig`) by default. The Smarty back office (`templates/backOffice/default/`) stays available so projects migrating from Thelia 2 can keep modules that target it. You can install both at once and switch the active one:

```bash
ddev exec bin/console template:set backOffice default-twig   # or: default
```

If you maintain a module, the migration guide is in `BREAKING_CHANGES.md` inside the default-twig template.

## Tests and quality

```bash
ddev exec composer test       # PHPUnit test suites
ddev exec composer cs-diff    # coding standard (php-cs-fixer)
ddev exec composer phpstan    # static analysis
```

## How the packages fit together

Several packages are split out of this repository: `thelia/core`, `thelia/config` and `thelia/setup`. Modules live under [thelia-modules](https://github.com/thelia-modules), and the Flexy front-office theme is in [thelia/Flexy](https://github.com/thelia/Flexy).

## Contributing

Pull requests go to this repository. See [CONTRIBUTING.md](CONTRIBUTING.md) for the coding standard and the test workflow.

## Community

- Documentation: <https://doc.thelia.net>
- Website: <https://thelia.net>
- Discord: <https://discord.gg/YgwpYEE3y3>
- Forum: <https://forum.thelia.net/>
