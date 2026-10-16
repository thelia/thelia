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

namespace Thelia\Tests\Unit\Core\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Thelia\Core\DependencyInjection\Compiler\HandledMessageClassesPass;

final class HandledMessageClassesPassTest extends TestCase
{
    /**
     * A handler taking any message would let every class of the shop out of a queue
     * again: only handlers of named classes count.
     */
    public function testOnlyTheHandlersOfNamedClassesCount(): void
    {
        $container = new ContainerBuilder();
        $container->register('messenger.bus.default.messenger.handlers_locator')->setArgument(0, ['*' => [], 'object' => [], 'App\Message\SyncStock' => []]);

        (new HandledMessageClassesPass())->process($container);

        self::assertSame(['App\Message\SyncStock'], $container->getParameter(HandledMessageClassesPass::PARAMETER));
    }
}
