# Thelia

[![tests](https://img.shields.io/github/actions/workflow/status/thelia/thelia/test.yml?branch=main&label=tests)](https://github.com/thelia/thelia/actions/workflows/test.yml) [![PHPStan](https://img.shields.io/github/actions/workflow/status/thelia/thelia/phpstan-botwig.yml?branch=main&label=PHPStan)](https://github.com/thelia/thelia/actions/workflows/phpstan-botwig.yml)
[![stable](https://img.shields.io/packagist/v/thelia/thelia?label=stable)](https://packagist.org/packages/thelia/thelia) [![PHP](https://img.shields.io/badge/PHP-8.3-777BB4?logo=php&logoColor=white)](https://www.php.net/releases/8.3/en.php) [![Symfony](https://img.shields.io/badge/Symfony-7.4%20LTS-000000?logo=symfony&logoColor=white)](https://symfony.com/releases/7.4) [![API Platform](https://img.shields.io/badge/API%20Platform-4.3-38A9B4)](https://api-platform.com) [![license](https://img.shields.io/packagist/l/thelia/thelia)](LICENSE) [![docs](https://img.shields.io/badge/docs-doc.thelia.net-0A7BBB)](https://doc.thelia.net)
[![stars](https://img.shields.io/github/stars/thelia/thelia?label=stars)](https://github.com/thelia/thelia/stargazers) [![contributors](https://img.shields.io/github/contributors/thelia/thelia)](https://github.com/thelia/thelia/graphs/contributors) [![last commit](https://img.shields.io/github/last-commit/thelia/thelia/main)](https://github.com/thelia/thelia/commits/main) [![code quality](https://img.shields.io/scrutinizer/quality/g/thelia/thelia/main?label=code%20quality)](https://scrutinizer-ci.com/g/thelia/thelia/)

This is the development repository of Thelia, the open source e-commerce framework. Work on Thelia itself here.

To create a shop, use the project skeleton instead: [thelia/thelia-project](https://github.com/thelia/thelia-project).

## About

Thelia is an open source framework for building online stores and managing web content. Version 3 runs on:

- PHP 8.3, 8.4 or 8.5
- Symfony 7.4 LTS
- API Platform 4.3 (standalone)
- Propel ORM
- A Twig front office (the Flexy theme) and a Twig back office (the default-twig theme)
- Lexik JWT for API authentication

The back office and front office are built with Twig, Symfony UX (Stimulus, Twig Components and Live Components) and Bootstrap 5. The Smarty back office from Thelia 2 is still available for projects that need it while they migrate. See "Back-office templates" below.

Thelia is open source software. See the [LICENSE](LICENSE) file for details.

## Requirements

| Requirement | Supported |
| --- | --- |
| PHP | 8.3, 8.4 or 8.5 (8.3 recommended) |
| Database | MariaDB 10.11 or later (recommended), or MySQL 8.x |
| PHP extensions | pdo_mysql, openssl, intl, gd, curl, dom, mbstring, zip |
| Composer | 2.7 or later |
| Web server | Nginx or Apache, document root set to `public/` |

How long each release series receives security fixes is listed in [SECURITY.md](SECURITY.md).

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

`bin/install` compiles all the assets itself: it runs `importmap:install` and `tailwind:build` for the active front-office theme, and `sass:build` for the back-office stylesheet. To rebuild them later, run the same commands from the application root:

```bash
ddev exec php bin/console importmap:install   # front-office JavaScript dependencies
ddev exec php bin/console tailwind:build      # front-office stylesheet (Flexy)
ddev exec php bin/console sass:build          # back-office stylesheet (default-twig)
```

The storefront is then at `https://<project>.ddev.site` and the admin at `https://<project>.ddev.site/admin`.

## Back-office templates

Thelia 3 installs the Twig back office (`default-twig`) by default. The Smarty back office (`templates/backOffice/default/`) stays available so projects migrating from Thelia 2 can keep modules that target it. You can install both at once and switch the active one:

```bash
ddev exec bin/console template:set backOffice default-twig   # or: default
```

If you maintain a module, the migration guide is at <https://doc.thelia.net/docs/upgrading/migrate>.

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
