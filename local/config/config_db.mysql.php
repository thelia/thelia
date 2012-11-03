<?php

// database acces configuration for mysql
// ---------------------------------------------

//database type : mysql, sqlite, pgsql, etc
define('THELIA_DB_TYPE','mysql');

// database login
define('THELIA_BD_LOGIN', '__DB_LOGIN__');

// database password
define('THELIA_BD_PASSWORD', '__DB_PASSWORD__');

//database DSN
define('THELIA_DB_DSN','mysql:dbname=__DB_NAME__;host:__DB_HOST__');
