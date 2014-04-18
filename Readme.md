Readme
======

Thelia
------
[![Build Status](https://travis-ci.org/thelia/thelia.png?branch=master)](https://travis-ci.org/thelia/thelia) [![License](https://poser.pugx.org/thelia/thelia/license.png)](https://packagist.org/packages/thelia/thelia) [![Scrutinizer Quality Score](https://scrutinizer-ci.com/g/thelia/thelia/badges/quality-score.png?s=61e3e04a69bffd71c29b08e5392080317a546716)](https://scrutinizer-ci.com/g/thelia/thelia/)

[Thelia](http://thelia.net/v2) is an open source tool for creating e-business websites and managing online content. This software is published under GPL.

Here is the most recent developed code for the next major version (v2). You can download this version for testing or having a look on the code (or anything you wish, respecting GPL). See http://thelia.net/v2 web site for more information.

Most part of the code can possibly change, a large part will be refactor soon, graphical setup does not exist yet.

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
export PATH=/Applications/MAMP/bin/php/php5.4.x/bin/:$PATH
```

* configure a complete development environment : http://php-osx.liip.ch/
* use a virtual machine with vagrant and puppet : https://puphpet.com/

## Download Thelia 2

``` bash
$ curl -sS https://getcomposer.org/installer | php
$ php composer.phar create-project thelia/thelia path/ 2.0.0-RC1
```

## Install it

You can install Thelia by two different way

### Using install wizard

Installing thelia with the web install wizard allow to create an administrator, add some informations about your shop, etc

First of all, you have to configure a vhost as describe in [configuration](/en/documentation/configuration.html) section.

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

