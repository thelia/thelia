Readme
======

Thelia 
------

Thelia is an ecommerce CMS.

Here is the current developping next major version. You can download this version for testing or see the code.

Most part of the code can change, a large part will be refactor soon, configuration system does not exists yet.

Installation
------------

``` bash
$ git clone --recursive https://github.com/thelia/thelia.git
$ cd thelia
$ wget http://getcomposer.org/composer.phar
$ php composer.phar install
```
create a mysql database and import sql dump files located in install folder. First thelia.sql and then insert.sql

Configure mysql connection : 
``` bash
$ cd local/config
$ cp config_db.mysql.php config_db.php
```

edit config_db.php file and replace with mysql connection parameters : 
- __DB_LOGIN__ replace with your mysql user 
- __DB_PASSWORD__ replace with your mysql password
- __DB_NAME__ replace with you database name
- __DB_HOST__ replace with your mysql host

Usage
-----

Consult the page : http://localhost/thelia/web/index_dev.php

You can create a virtual host and choose web folder for root directory.

For running test : 

``` bash
$ phpunit
```

We have lot of work to do but enjoy this part.







