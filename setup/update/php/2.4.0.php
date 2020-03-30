<?php
/** @var PDO $pdo */
$pdo = $database->getConnection();

// Store the current last order ID in a configuration variable to ensure existing orders will not
// be impacted by new rounding rules introduced by Thelia 2.4 (see https://github.com/thelia/thelia/pull/2735/files)

// Get the last order ID
$sql = 'SELECT id FROM `order` ORDER BY `id` DESC LIMIT 1';

$stmt = $pdo->query($sql);
$queryResult = $stmt->fetch(\PDO::FETCH_OBJ);

$lastOrderId = isset($queryResult->id) ? $queryResult->id : 0;

// Store it in an hidden configuration variable
$sql = "INSERT INTO `config` (`name`, `value`, `secured`, `hidden`, `created_at`, `updated_at`) VALUES ('last_legacy_rounding_order_id', ?, 1, 1, ?, ?)";

$now = (new \DateTime())->format('Y-m-d H:i:s');

$pdo->prepare($sql)->execute([ $lastOrderId, $now, $now ]);
