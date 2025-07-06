<?php

declare(strict_types=1);

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

$pdo = $database->getConnection();

// Test if price columns are in float
$sqlGetFloat = 'SELECT COLUMN_NAME '
  . 'FROM INFORMATION_SCHEMA.COLUMNS '
  . "WHERE TABLE_SCHEMA LIKE DATABASE() AND TABLE_NAME LIKE 'product_price' AND COLUMN_NAME LIKE 'price' AND COLUMN_TYPE LIKE 'float%'";

$stmtGetFloat = $pdo->query($sqlGetFloat);

// alter tables to convert float to decimal
if (0 !== $stmtGetFloat->rowCount()) {
    $columns = [
        ['product_price', 'price'],
        ['product_price', 'promo_price'],
        ['order_product', 'price'],
        ['order', 'discount'],
        ['order', 'postage'],
        ['order', 'postage_tax'],
        ['order_version', 'discount'],
        ['order_version', 'postage'],
        ['order_version', 'postage_tax'],
        ['order_product_tax', 'amount'],
        ['order_product_tax', 'promo_amount'],
        ['cart', 'discount'],
        ['cart_item', 'price'],
        ['cart_item', 'promo_price'],
        ['order_coupon', 'amount'],
    ];

    $queries = [
        "ALTER TABLE `:table:` ADD COLUMN `:column:_temp` DECIMAL( 16, 6 ) NOT NULL DEFAULT '0.00000000' AFTER `:column:`",
        'UPDATE `:table:` SET `:column:_temp` = CAST(`:column:` as CHAR)',
        'ALTER TABLE `:table:` DROP COLUMN `:column:`',
        "ALTER TABLE `:table:` CHANGE COLUMN `:column:_temp` `:column:` DECIMAL( 16, 6 ) NOT NULL DEFAULT '0.00000000'",
    ];

    foreach ($columns as $column) {
        $args = [
            ':table:' => $column[0],
            ':column:' => $column[1],
        ];

        foreach ($queries as $query) {
            $stmtConvert = $pdo->prepare(strtr($query, $args));
            $stmtConvert->execute();
        }
    }

    $stmtConvert = $pdo->prepare("ALTER TABLE `order_product` CHANGE `promo_price` `promo_price` DECIMAL( 16, 6 ) NOT NULL DEFAULT '0.00000000'");
    $stmtConvert->execute();
}
