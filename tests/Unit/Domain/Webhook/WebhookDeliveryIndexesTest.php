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

namespace Thelia\Tests\Unit\Domain\Webhook;

use PHPUnit\Framework\TestCase;

/**
 * The delivery log is declared in three places a fresh install and an update read
 * separately: nothing regenerates `setup/thelia.sql` for the core. The indexes the API
 * and the back office filters rely on have to be in all three.
 */
final class WebhookDeliveryIndexesTest extends TestCase
{
    private const array EXPECTED = [
        'idx_webhook_delivery_created_at',
        'idx_webhook_delivery_event_created',
        'idx_webhook_delivery_status_created',
        'idx_webhook_delivery_subscription_created',
        'idx_webhook_delivery_subscription_status_delivered',
        'webhook_delivery_uuid_UNIQUE',
    ];

    public function testTheModelTheFreshInstallAndTheUpdateDeclareTheSameIndexes(): void
    {
        $root = \dirname(__DIR__, 4);

        $schema = (string) file_get_contents($root.'/local/config/schema.xml');
        self::assertSame(1, preg_match('#<table name="webhook_delivery".*?</table>#s', $schema, $table));
        preg_match_all('#<(?:index|unique) name="([^"]+)"#', $table[0], $fromSchema);

        $names = ['schema.xml' => $fromSchema[1]];

        foreach (['setup/thelia.sql' => 'CREATE TABLE `webhook_delivery`', 'setup/update/sql/3.3.0.sql' => 'CREATE TABLE IF NOT EXISTS `webhook_delivery`'] as $file => $start) {
            $sql = (string) file_get_contents($root.'/'.$file);
            $from = strpos($sql, $start);
            self::assertNotFalse($from, $file);
            $definition = substr($sql, $from, (int) strpos($sql, 'ENGINE=', $from) - $from);
            preg_match_all('#(?:UNIQUE )?INDEX `([^`]+)`#', $definition, $found);
            $names[$file] = $found[1];
        }

        foreach ($names as $file => $declared) {
            sort($declared);
            self::assertSame(self::EXPECTED, $declared, $file);
        }
    }
}
