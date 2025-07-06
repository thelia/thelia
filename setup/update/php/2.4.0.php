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

$pdo = $database->getConnection();

// Store the current last order ID in a configuration variable to ensure existing orders will not
// be impacted by new rounding rules introduced by Thelia 2.4 (see https://github.com/thelia/thelia/pull/2735/files)

// Get the last order ID
$sql = 'SELECT id FROM `order` ORDER BY `id` DESC LIMIT 1';

$stmt = $pdo->query($sql);
$queryResult = $stmt->fetch(PDO::FETCH_OBJ);

$lastOrderId = $queryResult->id ?? 0;

// Store it in an hidden configuration variable
$sql = "INSERT INTO `config` (`name`, `value`, `secured`, `hidden`, `created_at`, `updated_at`) VALUES ('last_legacy_rounding_order_id', ?, 1, 1, ?, ?)";

$now = (new DateTime())->format('Y-m-d H:i:s');

$pdo->prepare($sql)->execute([$lastOrderId, $now, $now]);

// Add ignored_module_hook.created_at if not exist
$ignoredModuleHookCreatedAtColumnExitSql = "SHOW COLUMNS FROM `ignored_module_hook` LIKE 'created_at'";
if (0 === count($pdo->query($ignoredModuleHookCreatedAtColumnExitSql)->fetchAll())) {
    $addIgnoredModuleHookCreatedAtColumnSql = 'ALTER TABLE `ignored_module_hook` ADD `created_at` DATETIME NOT NULL;';
    $pdo->query($addIgnoredModuleHookCreatedAtColumnSql);
}

// Add ignored_module_hook.updated_at if not exist
$ignoredModuleHookUpdatedAtColumnExitSql = "SHOW COLUMNS FROM `ignored_module_hook` LIKE 'updated_at'";
if (0 === count($pdo->query($ignoredModuleHookUpdatedAtColumnExitSql)->fetchAll())) {
    $addIgnoredModuleHookUpdatedAtColumnSql = 'ALTER TABLE `ignored_module_hook` ADD `updated_at` DATETIME NOT NULL;';
    $pdo->query($addIgnoredModuleHookUpdatedAtColumnSql);
}
