Readme
======

## Warning
### This is the development repository of Thelia. If you want to create a project, please take a look at [thelia/thelia-project](https://github.com/thelia/thelia-project)

Thelia
------
[![Build Status](https://travis-ci.org/thelia/thelia.png?branch=master)](https://travis-ci.org/thelia/thelia) [![License](https://poser.pugx.org/thelia/thelia/license.png)](https://packagist.org/packages/thelia/thelia) [![Scrutinizer Quality Score](https://scrutinizer-ci.com/g/thelia/thelia/badges/quality-score.png?s=61e3e04a69bffd71c29b08e5392080317a546716)](https://scrutinizer-ci.com/g/thelia/thelia/)

[Thelia](http://thelia.net/) is an open source tool for creating e-business websites and managing online content. This software is published under LGPL.

This is the new major version of Thelia.

You can download this version and have a try or take a look at the  source code (or anything you wish, respecting LGPL).  See http://thelia.net/ web site for more information.

A repository containing all thelia modules is available at this address : https://github.com/thelia-modules

Requirements
------------

* php 5.4
    * Required extensions :
        * PDO_Mysql
        * mcrypt
        * intl
        * gd
        * curl
    * safe_mode off
    * memory_limit at least 128M, preferably 256.
    * post_max_size 20M
    * upload_max_filesize 2M
* apache 2
* mysql 5

If you use Mac OSX, it still doesn't use php 5.4 as default php version... There are many solutions for you :

* use [phpbrew](https://github.com/c9s/phpbrew)
* use last MAMP version and put the php bin directory in your path:

```bash
export PATH=/Applications/MAMP/bin/php/php5.5.x/bin/:$PATH
```

* configure a complete development environment : http://php-osx.liip.ch/
* use a virtual machine with vagrant and puppet : https://puphpet.com/

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

For more information on sql_mode you can consult the [MySQL doc](http://dev.mysql.com/doc/refman/5.0/fr/server-sql-mode.html "sql Mode")

## Archive builders
Thelia's archive builder's needs external libraries.
For zip archives, you need PECL zip. See [PHP Doc](http://php.net/manual/en/zip.installation.php)

For tar archives, you need PECL phar. Moreover, you need to deactivate php.ini option "phar.readonly":
```ini
phar.readonly = Off
```

For tar.bz2 archives, you need tar's dependencies and the extension "bzip2". See [PHP Doc](http://php.net/manual/fr/book.bzip2.php)

For tar.gz archives, you need tar's dependencies and the extension "zlib". See [PHP Doc](http://fr2.php.net/manual/fr/book.zlib.php)

## Download Thelia 2 and install its dependencies

You can get the sources from git and then let composer install dependencies, or use composer to install the whole thelia project into a specific directory

### Using git for download and composer for dependencies
``` bash
$ git clone --recursive https://github.com/thelia/thelia path
$ cd path
$ git checkout 2.0.5 (or 2.1.0)
$ curl -sS https://getcomposer.org/installer | php
$ php composer.phar install
```

### Using composer for both download and dependencies
``` bash
$ curl -sS https://getcomposer.org/installer | php
$ php composer.phar create-project thelia/thelia path/ 2.0.5 (or 2.1.0)
```

## Install it

You can install Thelia by two different way

### Using install wizard

Installing thelia with the web install wizard allow to create an administrator, add some informations about your shop, etc

First of all, you have to configure a vhost as describe in [configuration](http://doc.thelia.net/en/documentation/configuration.html) section.

The install wizard in accessible with your favorite browser :

``` bash
http://yourdomain.tld/[/subdomain_if_needed]/install
```

For example, I have thelia downloaded at http://thelia.net and my vhost is correctly configured, I have to reach this address :

``` bash
http://thelia.net/install
```

### Using cli tools

``` bash
$ php Thelia thelia:install
```

You just have to follow all instructions.

Documentation
-------------

Thelia documentation is available at http://doc.thelia.net

The documentation is also in beta version and some part can be obsolete cause to some refactor.


Roadmap
-------

The Roadmap is available at http://thelia.net/community/roadmap


Contribute
----------

see the documentation : http://doc.thelia.net/en/documentation/contribute.html

Usage
-----

Consult the page : http://localhost/thelia/web/index_dev.php

You can create a virtual host and choose web folder for root directory.

To run tests (phpunit required) :

``` bash
$ phpunit
```

We still have lot of work to achieve but enjoy this part.

