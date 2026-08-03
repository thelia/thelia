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

// Enforce invoice_ref uniqueness. Some shops let a third-party invoicing
// module write this column, and a few of them produced duplicates: in that
// case skip the index (the core allocator still guarantees uniqueness for the
// numbers it hands out) instead of failing the whole update.
$hasIndex = 0 !== count($pdo->query("SHOW INDEX FROM `order` WHERE Key_name = 'invoice_ref_UNIQUE'")->fetchAll());

if (!$hasIndex) {
    // Unlike NULLs, several empty strings DO count as duplicates for a unique
    // index: normalize them first, or the ALTER below would abort the update
    // on any shop where a module left more than one empty invoice_ref.
    $pdo->query('UPDATE `order` SET `invoice_ref` = NULL WHERE `invoice_ref` = ""');

    $duplicates = $pdo->query(
        'SELECT `invoice_ref` FROM `order` WHERE `invoice_ref` IS NOT NULL GROUP BY `invoice_ref` HAVING COUNT(*) > 1 LIMIT 1',
    )->fetchAll();

    if (0 === count($duplicates)) {
        $pdo->query('ALTER TABLE `order` ADD UNIQUE INDEX `invoice_ref_UNIQUE` (`invoice_ref`)');
    }
}
