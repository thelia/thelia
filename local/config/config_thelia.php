<?php
$conf = array (
  'datasources' => 
  array (
    'thelia' => 
    array (
      'adapter' => THELIA_DB_ADAPTER,
      'connection' => 
      array (
        'dsn' => THELIA_DB_DSN,
        'user' => THELIA_DB_USER,
        'password' => THELIA_DB_PASSWORD,
      ),
    ),
  ),
  'generator_version' => '1.6.8',
);
return $conf;