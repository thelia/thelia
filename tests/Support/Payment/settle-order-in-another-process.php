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

/*
 * Takes the locks of a settlement from a session of its own, in the order the core takes
 * them: the order by its primary key, then its parts. Run by ModuleDeletionFreezeConcurrencyTest.
 *
 * Usage: php settle-order-in-another-process.php <order id> <marker file> <pause in ms>
 *
 * The connection comes from the environment (FREEZE_TEST_DSN, FREEZE_TEST_USER,
 * FREEZE_TEST_PASSWORD). The marker file is written once the order is locked; after the
 * pause the parts are locked. Prints JSON: the SQLSTATE driver code of the failure, or null.
 */

$orderId = (int) ($argv[1] ?? 0);
$markerFile = (string) ($argv[2] ?? '');
$pauseMilliseconds = (int) ($argv[3] ?? 1500);

$pdo = new PDO(
    (string) getenv('FREEZE_TEST_DSN'),
    (string) getenv('FREEZE_TEST_USER'),
    (string) getenv('FREEZE_TEST_PASSWORD'),
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
);

$errorCode = null;

try {
    $pdo->beginTransaction();
    $pdo->query('SELECT `id` FROM `order` WHERE `id` = '.$orderId.' FOR UPDATE')->closeCursor();
    file_put_contents($markerFile, (string) getmypid());
    usleep($pauseMilliseconds * 1000);
    $pdo->query('SELECT `id` FROM `order_payment_part` WHERE `order_id` = '.$orderId.' ORDER BY `id` FOR UPDATE')->closeCursor();
    $pdo->commit();
} catch (PDOException $exception) {
    $errorCode = $exception->errorInfo[1] ?? $exception->getCode();
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
}

echo json_encode(['errorCode' => $errorCode], \JSON_THROW_ON_ERROR);
