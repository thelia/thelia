Readme
======

Thelia 
------

Thelia is an open source tool for creating e-business websites and managing online content. This software is published under GPL.

Here is the current developping next major version. You can download this version for testing or see the code.
Here is the most recent developed code for the next major version (v2). You can download this version for testing or having a look on the code (or anything you wish, respecting GPL).

Most part of the code can possibly change, a large part will be refactor soon, graphical setup does not exist yet.

Installation
------------

``` bash
$ git clone --recursive https://github.com/thelia/thelia.git
$ cd thelia
$ wget http://getcomposer.org/composer.phar
$ php composer.phar install
```
Create a mysql database and import sql dump files located in the install folder : thelia.sql first, then insert.sql.

Configure mysql connection : 

* rename ```local/config/database.yml.sample"``` into ```local/config/database.yml```
* edit ```local/config/database.yml``` file and add needed information

Usage
-----

Consult the page : http://localhost/thelia/web/index_dev.php

You can create a virtual host and choose web folder for root directory.

To run tests (phpunit required) :

``` bash
$ phpunit
```

We still have lot of work to achieve but enjoy this part.

