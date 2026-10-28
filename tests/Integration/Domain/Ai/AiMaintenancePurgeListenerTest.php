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

namespace Thelia\Tests\Integration\Domain\Ai;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Cache\PruneableInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Thelia\Core\Event\Maintenance\MaintenancePurgeEvent;
use Thelia\Domain\Ai\EventListener\AiMaintenancePurgeListener;
use Thelia\Domain\Ai\Log\AiLogPurger;
use Thelia\Domain\Ai\Service\AiSettings;
use Thelia\Test\IntegrationTestCase;

/**
 * The sessions of the MCP server live in a pool that `cache:clear` never empties: the maintenance prunes it.
 */
final class AiMaintenancePurgeListenerTest extends IntegrationTestCase
{
    public function testTheMaintenancePrunesTheSessionPoolOfTheMcpServer(): void
    {
        $pool = $this->createMockForIntersectionOfInterfaces([CacheItemPoolInterface::class, PruneableInterface::class]);
        $pool->expects(self::once())->method('prune')->willReturn(true);
        $event = new MaintenancePurgeEvent();

        $this->listener($pool)->onMaintenancePurge($event);

        self::assertStringContainsString('AI MCP sessions (expired):</comment> <info>pruned', implode("\n", $event->getResults()));
    }

    public function testADryRunPrunesNothing(): void
    {
        $pool = $this->createMockForIntersectionOfInterfaces([CacheItemPoolInterface::class, PruneableInterface::class]);
        $pool->expects(self::never())->method('prune');
        $event = new MaintenancePurgeEvent(true);

        $this->listener($pool)->onMaintenancePurge($event);

        self::assertStringContainsString('to prune', implode("\n", $event->getResults()));
    }

    public function testTheCommandReachesTheRealPool(): void
    {
        $tester = new CommandTester((new Application(self::$kernel))->find('maintenance:purge'));
        $tester->execute(['--dry-run' => true]);

        $tester->assertCommandIsSuccessful();
        self::assertStringContainsString('AI MCP sessions (expired)', $tester->getDisplay());
    }

    private function listener(CacheItemPoolInterface $pool): AiMaintenancePurgeListener
    {
        return new AiMaintenancePurgeListener(self::getContainer()->get(AiLogPurger::class), new AiSettings(), $pool);
    }
}
