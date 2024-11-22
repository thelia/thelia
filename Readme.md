Readme
======

## Warning
### This is the development repository of Thelia. If you want to create a project, please take a look at [thelia/thelia-project](https://github.com/thelia/thelia-project)

If you want to download a packaged, ready-to-use distribution of the most recent version of Thelia please download [thelia.zip](https://thelia.net/download/thelia.zip)

Thelia
------
[![Actions Status: test](https://github.com/thelia/thelia/workflows/test/badge.svg?branch=main)](https://github.com/thelia/thelia/actions?query=workflow%3A"test")
[![Scrutinizer Quality Score](https://scrutinizer-ci.com/g/thelia/thelia/badges/quality-score.png?s=61e3e04a69bffd71c29b08e5392080317a546716)](https://scrutinizer-ci.com/g/thelia/thelia/)
![Discord Shield](https://discordapp.com/api/guilds/1067836968689487872/widget.png?style=shield)

[Thelia](https://thelia.net/) is an open source tool for creating e-business websites and managing online content. This software is published under GNU General Public License v3.0.

A repository containing all thelia modules is available at this address : https://github.com/thelia-modules

Compatibility
------------

|         |   Thelia 2.3    |      Thelia 2.4 |    Thelia 2.5 |
|---------|:---------------:|----------------:|--------------:|
| PHP     | 5.5 5.6 7.0 7.1 | 7.0 7.1 7.2 7.3 | 8.0.2 8.1 8.2 |
| MySQL   |     5.5 5.6     |     5.5 5.6 5.7 |   5.6 5.7 8.0 |
| Symfony |       2.8       |             2.8 |       6.0 6.3 |

Requirements
------------

* PHP
    * Required extensions :
        * PDO_Mysql
        * openssl
        * intl
        * gd
        * curl
        * dom
    * safe_mode off
    * memory_limit at least 128M, preferably 256.
    * post\_max\_size 20M
    * upload\_max\_filesize 2M
    * date.timezone must be defined
* Web Server Apache 2 or Nginx


### MySQL 5.6

As of MySQL 5.6, default configuration sets the sql_mode value to

```
STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION
```

This 'STRICT_TRANS_TABLES' configuration results in SQL errors when no default value is defined on NOT NULL columns and the value is empty or invalid.

You can edit this default config in ` /etc/my.cnf ` and change the sql_mode to remove the STRICT_TRANS_TABLES part

```
[mysqld]
sql_mode=NO_ENGINE_SUBSTITUTION
```

Assuming your sql_mode is the default one, you can change the value directly on the run by running the following SQL Command

```sql
SET @@GLOBAL.sql_mode='NO_ENGINE_SUBSTITUTION', @@SESSION.sql_mode='NO_ENGINE_SUBSTITUTION'
```

For more information on sql_mode you can consult the [MySQL doc](https://dev.mysql.com/doc/refman/5.0/fr/server-sql-mode.html "sql Mode")

## Archive builders
Thelia's archive builder's needs external libraries.
For zip archives, you need PECL zip. See [PHP Doc](https://php.net/manual/en/zip.installation.php)

For tar archives, you need PECL phar. Moreover, you need to deactivate php.ini option "phar.readonly":
```ini
phar.readonly = Off
```

For tar.bz2 archives, you need tar's dependencies and the extension "bzip2". See [PHP Doc](https://php.net/manual/fr/book.bzip2.php)

For tar.gz archives, you need tar's dependencies and the extension "zlib". See [PHP Doc](https://fr2.php.net/manual/fr/book.zlib.php)

## Download Thelia 2 and install its dependencies

You can get the sources from git and then let composer install dependencies, or use composer to install the whole thelia project into a specific directory

### Using git for download and composer for dependencies

``` bash
$ git clone --recursive https://github.com/thelia/thelia path
$ cd path
$ git checkout 2.5.4
$ curl -sS https://getcomposer.org/installer | php
$ php composer.phar install
```

### Using composer for both download and dependencies

``` bash
$ curl -sS https://getcomposer.org/installer | php
$ php composer.phar create-project thelia/thelia path/ 2.5.4
```

If something goes wrong during the install process, you can restart Thelia install wizard with
the following command : `php composer.phar run-script post-create-project-cmd`

## Install it

You can install Thelia by different way

### Using install wizard

Installing thelia with the web install wizard allow to create an administrator, add some informations about your shop, etc

First of all, you have to configure a vhost as describe in [configuration](https://doc.thelia.net/en/documentation/configuration.html) section.

The install wizard in accessible with your favorite browser :

``` bash
https://yourdomain.tld/[/subdomain_if_needed]/install
```

For example, I have thelia downloaded at https://thelia.net and my vhost is correctly configured, I have to reach this address :

``` bash
https://thelia.net/install
```

### Using cli tools

``` bash
$ php Thelia thelia:install
```

You just have to follow all instructions.

### Docker and docker compose

This repo contains all the configuration needed to run Thelia with docker and docker-compose.
Warning, this docker configuration is not ready for production.

It requires obviously [docker](https://docker.com/) and [docker-compose](https://docs.docker.com/compose/)

Please, follow the official documentation to install docker and docker-compose on your system :
https://doc.thelia.net/docs/getting_started/docker


Contribute
----------

See the documentation : https://doc.thelia.net/docs/contribute


If you submit modifications that adds new data or change the structure of the database, take a look to https://doc.thelia.net/docs/contribute#sql-scripts-modification


## Community

Join the conversation and help the community :

[Twitter](https://github.com/thelia/thelia)
[Discord](https://discord.gg/YgwpYEE3y3)
[StackOverflow](https://stackoverflow.com/questions/tagged/thelia)
[Forum](https://forum.thelia.net/)
