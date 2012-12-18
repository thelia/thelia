<?php

// database acces configuration for mysql
// ---------------------------------------------

//database type : mysql, sqlite, pgsql, etc
define('THELIA_DB_ADAPTER','mysql');

// database login
define('THELIA_DB_USER', '__DB_LOGIN__');

// database password
define('THELIA_DB_PASSWORD', '__DB_PASSWORD__');

//database DSN
define('THELIA_DB_DSN','mysql:dbname=__DB_NAME__;host:__DB_HOST__');
