Readme
======

## Warning
### This is the development repository of Thelia. If you want to create a project, please take a look at [thelia/thelia-project](https://github.com/thelia/thelia-project)

Thelia
------

[![Build Status](https://travis-ci.org/thelia/thelia.png?branch=2.3)](https://travis-ci.org/thelia/thelia) [![License](https://poser.pugx.org/thelia/thelia/license.png)](https://packagist.org/packages/thelia/thelia) [![Scrutinizer Quality Score](https://scrutinizer-ci.com/g/thelia/thelia/badges/quality-score.png?s=61e3e04a69bffd71c29b08e5392080317a546716)](https://scrutinizer-ci.com/g/thelia/thelia/)

[Thelia](http://thelia.net/) is an open source tool for creating e-business websites and managing online content. This software is published under LGPL.

This is the new major version of Thelia.

A repository containing all thelia modules is available at this address : https://github.com/thelia-modules

Requirements
------------

* PHP 5.4
    * Required extensions :
        * PDO_Mysql
        * mcrypt
        * intl
        * gd
        * curl
    * safe_mode off
    * memory_limit at least 128M, preferably 256.
    * post\_max\_size 20M
    * upload\_max\_filesize 2M
    * date.timezone must be defined
* Web Server Apache 2 or Nginx
* MySQL 5


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
$ git checkout 2.3.3 (2.2.6 or 2.1.11)
$ curl -sS https://getcomposer.org/installer | php
$ php composer.phar install
```

### Using composer for both download and dependencies

``` bash
$ curl -sS https://getcomposer.org/installer | php
$ php composer.phar create-project thelia/thelia path/ 2.3.3 (2.2.6 or 2.1.11)
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

### Docker and docker compose

This repo contains all the configuration needed to run Thelia with docker and docker-compose.

It requires obviously [docker](https://docker.com/) and [docker-compose](http://docs.docker.com/compose/)

How to start the configuration : 

```
docker-compose up -d
```

tip : create an alias for docker-compose, it's boring to write it all the time

All the script are launched through docker. For examples : 

```
docker exec -it thelia_web_1 php Thelia cache:clear
docker exec -it thelia_web_1 php setup/faker.php
docker exec -it thelia_web_1 unit-tests.sh
docker exec -it thelia_web_1 php composer.phar install
```

Database information : 

* host : mariaDB
* login : root
* password : toor

Once started, you can access it with your browser at this url : http://127.0.0.1:8080 and phpmyadmin : http://127.0.0.1:8081

What is missing : 

* confguration for export compression (zip, gzip, etc)

Obviously you can modify all the configuration for your own case, for example the php version or add environment variable for the database configuration. Each time you modify the configuration, you have to rebuild it : 

```
docker-compose build --no-cache
```

Documentation
-------------

Thelia documentation is available at http://doc.thelia.net


Roadmap
-------

The Roadmap is available at http://thelia.net/community/roadmap


Contribute
----------

see the documentation : http://doc.thelia.net/en/documentation/contribute.html

If you submit modifications that adds new data or change the structure of the database, take a look to http://doc.thelia.net/en/documentation/contribute.html#sql-scripts-modification

Usage
-----

Consult the page : http://localhost/thelia/web/index_dev.php

You can create a virtual host and choose web folder for root directory.

To run tests (phpunit required) :

``` bash
$ phpunit
```

We still have lot of work to achieve but enjoy this part.