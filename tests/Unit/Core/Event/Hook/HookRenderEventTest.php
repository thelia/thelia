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

namespace Thelia\Tests\Unit\Core\Event\Hook;

use PHPUnit\Framework\TestCase;
use Thelia\Core\Event\Hook\HookRenderEvent;

/**
 * A hook render event carries the hook it was raised for and gathers the fragments the
 * listeners contribute, which the template then prints as one string.
 */
final class HookRenderEventTest extends TestCase
{
    public function testTheEventKeepsTheHookItWasRaisedFor(): void
    {
        $event = new HookRenderEvent('main.head-bottom', ['position' => 'top'], ['locale' => 'fr_FR']);

        self::assertSame('main.head-bottom', $event->getCode());
        self::assertSame(['position' => 'top'], $event->getArguments());
        self::assertSame(['locale' => 'fr_FR'], $event->getTemplateVars());
    }

    public function testFragmentsAreCollectedInTheOrderTheListenersAddThem(): void
    {
        $event = (new HookRenderEvent('main.head-bottom'))->add('<a>')->add('<b>');

        self::assertSame(['<a>', '<b>'], $event->get());
    }

    public function testDumpGluesTheFragmentsAndWrapsThemOnce(): void
    {
        $event = (new HookRenderEvent('main.navbar-primary'))->add('Home')->add('Shop');

        self::assertSame('<ul><li>Home</li><li>Shop</li></ul>', $event->dump('</li><li>', '<ul><li>', '</li></ul>'));
    }

    public function testDumpWithoutOptionsSimplyJoinsTheFragments(): void
    {
        self::assertSame('HomeShop', (new HookRenderEvent('main.navbar-primary'))->add('Home')->add('Shop')->dump());
    }

    public function testDumpOfAnEventNobodyAnsweredIsEmptyWhateverTheWrapping(): void
    {
        self::assertSame('', (new HookRenderEvent('main.navbar-primary'))->dump(', ', '<ul>', '</ul>'));
    }
}
