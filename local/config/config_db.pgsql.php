<?php

// database acces configuration for postgresql
// ---------------------------------------------

//database type : mysql, sqlite, pgsql, etc
define('THELIA_DB_TYPE','pgsql');

// database login
define('THELIA_BD_LOGIN', '__DB_LOGIN__');

// database password
define('THELIA_BD_PASSWORD', '__DB_PASSWORD__');

define('THELIA_DB_DSN','pgsql:dbname=__DB_NAME__;host:__DB_HOST__');
