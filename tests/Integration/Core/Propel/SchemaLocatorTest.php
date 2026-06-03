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

namespace Thelia\Tests\Integration\Core\Propel;

use Thelia\Core\Propel\Schema\SchemaLocator;
use Thelia\Test\IntegrationTestCase;

final class SchemaLocatorTest extends IntegrationTestCase
{
    private function createSchemaLocator(): SchemaLocator
    {
        return new SchemaLocator(
            THELIA_CONF_DIR,
            THELIA_MODULE_DIR,
            THELIA_LOCAL_MODULE_DIR,
        );
    }

    public function testFindForModulesReturnsCoreSchemas(): void
    {
        $schemas = $this->createSchemaLocator()->findForModules(['Thelia']);

        self::assertNotEmpty($schemas);
    }

    public function testFindForModulesSkipsModuleMissingFromDisk(): void
    {
        // A module can be active in the database while its code is missing from
        // disk (e.g. removed from composer while a previously populated database
        // is reused). The locator must skip it instead of crashing the boot.
        $schemas = $this->createSchemaLocator()->findForModules(['GhostModuleMissingFromDisk'], true);

        // Core schemas are still returned: 'Thelia' is always added as a dependency.
        self::assertNotEmpty($schemas);
    }

    public function testFindForModulesWithEmptyListReturnsNothing(): void
    {
        self::assertSame([], $this->createSchemaLocator()->findForModules([]));
    }
}
