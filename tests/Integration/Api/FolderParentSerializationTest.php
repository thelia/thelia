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

namespace Thelia\Tests\Integration\Api;

use Thelia\Api\Service\DataAccess\DataAccessService;
use Thelia\Test\IntegrationTestCase;

/**
 * The Folder resource must expose which folder a folder belongs to.
 *
 * Symfony's PropertyAccessor resolves a property through its accessor methods before the
 * public property itself, and tries an `is` accessor among them. Folder declared only
 * isParent(): bool, so `parent` was serialized as "does it have a parent" instead of the
 * parent id, and a client could not rebuild a folder tree from a collection response.
 * Category exposes the same property through getParent(): int and was unaffected.
 */
final class FolderParentSerializationTest extends IntegrationTestCase
{
    public function testFrontFolderExposesItsParentIdRatherThanABoolean(): void
    {
        $factory = $this->createFixtureFactory();

        $parent = $factory->folder();
        $factory->folder($parent->getId());

        $folders = static::getContainer()->get(DataAccessService::class)->resources(
            '/api/front/folders',
            ['parent' => $parent->getId(), 'visible' => true],
        );

        self::assertIsArray($folders);
        self::assertCount(1, $folders);
        self::assertSame($parent->getId(), $folders[0]['parent']);
    }
}
