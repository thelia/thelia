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

namespace Thelia\Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Thelia\Core\Propel\Schema\SchemaLocator;
use Thelia\Core\PropelInitService;

/**
 * The combined schema is what Propel builds the models from. A generation
 * interrupted after the directory was created leaves it empty, and taking that
 * directory for a cache made every boot die with "No schema files were found".
 */
final class PropelInitServiceTest extends TestCase
{
    private Filesystem $filesystem;

    private string $cacheDir;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->cacheDir = sys_get_temp_dir().'/thelia_propel_init_'.uniqid().\DIRECTORY_SEPARATOR;
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->cacheDir);
    }

    public function testAnEmptySchemaDirectoryIsNotACache(): void
    {
        $this->filesystem->mkdir($this->cacheDir.'schema');

        $service = $this->service();

        self::assertFalse($service->hasPropelGlobalSchema());
        self::assertTrue($service->buildPropelGlobalSchema());
        self::assertFileExists($this->cacheDir.'schema'.\DIRECTORY_SEPARATOR.'TheliaMain.schema.xml');
        self::assertFileExists($this->cacheDir.'hash');
    }

    public function testAGeneratedSchemaIsACache(): void
    {
        $service = $this->service();
        $service->buildPropelGlobalSchema();

        self::assertTrue($service->hasPropelGlobalSchema());
        self::assertFalse($service->buildPropelGlobalSchema());
    }

    private function service(): PropelInitService
    {
        $schemaLocator = $this->createMock(SchemaLocator::class);
        $schemaLocator
            ->method('findForAllModules')
            ->willReturn([$this->schemaDocument()]);

        $cacheDir = $this->cacheDir;

        return new class('test', false, [], $schemaLocator, $cacheDir) extends PropelInitService {
            private string $propelCacheDir;

            public function __construct($environment, $debug, array $envParameters, SchemaLocator $schemaLocator, string $propelCacheDir)
            {
                parent::__construct($environment, $debug, $envParameters, $schemaLocator);

                $this->propelCacheDir = $propelCacheDir;
            }

            public function getPropelCacheDir()
            {
                return $this->propelCacheDir;
            }
        };
    }

    private function schemaDocument(): \DOMDocument
    {
        $document = new \DOMDocument();
        $document->loadXML(
            <<<'XML'
                <?xml version="1.0" encoding="UTF-8"?>
                <database name="TheliaMain" defaultIdMethod="native" namespace="Thelia\Model">
                    <table name="test_table">
                        <column name="id" type="INTEGER" primaryKey="true" autoIncrement="true"/>
                    </table>
                </database>
                XML
        );

        return $document;
    }
}
