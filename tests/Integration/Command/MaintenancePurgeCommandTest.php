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

namespace Thelia\Tests\Integration\Command;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Thelia\Domain\Cart\Service\CartPurgeHorizon;
use Thelia\Model\ConfigQuery;
use Thelia\Test\IntegrationTestCase;

/**
 * The purge reads the cart retention where the conversion report reads it, so both
 * follow the same rule on a value the configuration screen does not bound.
 */
final class MaintenancePurgeCommandTest extends IntegrationTestCase
{
    protected function tearDown(): void
    {
        ConfigQuery::resetCache();
        parent::tearDown();
    }

    public function testANegativeCartRetentionIsReadAsZero(): void
    {
        ConfigQuery::write(CartPurgeHorizon::CONFIG_KEY_CART_NO_ORDER_DAYS, '-5');
        ConfigQuery::write(CartPurgeHorizon::CONFIG_KEY_CART_ANONYMOUS_DAYS, '-1');

        $tester = new CommandTester((new Application(self::$kernel))->find('maintenance:purge'));
        $tester->execute(['--dry-run' => true]);

        $tester->assertCommandIsSuccessful();
        self::assertStringContainsString('Carts without order (>0 days)', $tester->getDisplay());
        self::assertStringContainsString('Anonymous carts (>0 days)', $tester->getDisplay());
    }
}
