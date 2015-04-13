<?php

$secret = \Thelia\Tools\TokenProvider::generateToken();

$sql = "UPDATE `config` SET `value`=? WHERE `name`='form.secret'";

$database->execute($sql, [$secret]);
