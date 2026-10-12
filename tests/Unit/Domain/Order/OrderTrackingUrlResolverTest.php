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

namespace Thelia\Tests\Unit\Domain\Order;

use PHPUnit\Framework\TestCase;
use Thelia\Domain\Order\Service\OrderTrackingUrlResolver;

/**
 * What the back office accepts as a tracking address template: the spaces typed
 * around it do not count, and no invisible character may hide in it.
 */
final class OrderTrackingUrlResolverTest extends TestCase
{
    public function testSpacesTypedAroundATemplateDoNotMakeItInvalid(): void
    {
        self::assertTrue(OrderTrackingUrlResolver::isValidTemplate("  https://carrier.example/track?n=%ID% \t"));
    }

    public function testAnInvisibleFormatCharacterInsideATemplateMakesItInvalid(): void
    {
        self::assertFalse(OrderTrackingUrlResolver::isValidTemplate("https://carrier.example/track\u{FEFF}?n=%ID%"));
        self::assertFalse(OrderTrackingUrlResolver::isValidTemplate("https://carrier\u{FEFF}.example/track?n=%ID%"));
    }
}
