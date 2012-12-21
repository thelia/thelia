<?php

// database acces configuration for mysql
// ---------------------------------------------

//database type : mysql, sqlite, pgsql, etc
define('THELIA_DB_ADAPTER','oracle');

// database login
define('THELIA_DB_USER', '__DB_LOGIN__');

// database password
define('THELIA_DB_PASSWORD', '__DB_PASSWORD__');

//database DSN
define('THELIA_DB_DSN','mysql:dbname=__DB_NAME__;host:__DB_HOST__');

define('THELIA_DB_CACHE', 'file');
//define('THELIA_DB_CACHE', 'apc');
//define('THELIA_DB_CACHE', 'memcache');
//define('THELIA_DB_CACHE', 'session');
//define('THELIA_DB_CACHE', 'include');
