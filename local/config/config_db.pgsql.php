<?php

// database acces configuration for postgresql
// ---------------------------------------------

//database type : mysql, sqlite, pgsql, etc
define('THELIA_DB_ADAPTER','pgsql');

// database login
define('THELIA_DB_USER', '__DB_LOGIN__');

// database password
define('THELIA_BD_PASSWORD', '__DB_PASSWORD__');

//database dsn
define('THELIA_DB_DSN','pgsql:host=__DB_HOST__;port=__DB_PORT__;dbname=__DB_NAME__;user=__DB_LOGIN__;password=__DB_PASSWORD__');
