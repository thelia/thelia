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

namespace Thelia\Tests\Unit\Core\Event\File;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Thelia\Core\Event\File\FileCreateOrUpdateEvent;

/**
 * {@see FileCreateOrUpdateEvent::getParentName()} is typed string while the name is optional: the upload of the back
 * office hands it null, and a listener of IMAGE_SAVE that read it, or the "failed to be saved" message of
 * BaseCachedFile::saveFile(), stopped on a TypeError.
 */
final class FileCreateOrUpdateEventParentNameTest extends TestCase
{
    #[Test]
    public function aParentNameNeverGivenReadsAsAnEmptyString(): void
    {
        self::assertSame('', (new FileCreateOrUpdateEvent(3))->getParentName());
    }

    #[Test]
    public function aNullParentNameReadsAsAnEmptyString(): void
    {
        self::assertSame('', (new FileCreateOrUpdateEvent(3))->setParentName(null)->getParentName());
    }

    #[Test]
    public function aGivenParentNameIsReturned(): void
    {
        self::assertSame('Slider', (new FileCreateOrUpdateEvent(3))->setParentName('Slider')->getParentName());
    }
}
