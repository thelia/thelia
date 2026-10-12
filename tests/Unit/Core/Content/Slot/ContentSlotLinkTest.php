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

namespace Thelia\Tests\Unit\Core\Content\Slot;

use PHPUnit\Framework\TestCase;
use Thelia\Core\Content\Slot\ContentSlotLink;

/**
 * An entry of a content slot opens in the current window and holds no children,
 * unless it is built otherwise.
 */
final class ContentSlotLinkTest extends TestCase
{
    public function testAnEntryOpensInTheCurrentWindowAndHoldsNoChildrenByDefault(): void
    {
        $link = new ContentSlotLink(label: 'Terms', url: '/terms', source: 'cms_page', sourceId: 12);

        self::assertFalse($link->opensInNewWindow);
        self::assertSame([], $link->children);
    }
}
