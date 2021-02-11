<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

$secret = \Thelia\Tools\TokenProvider::generateToken();

$sql = "UPDATE `config` SET `value`=? WHERE `name`='form.secret'";

$database->execute($sql, [$secret]);
