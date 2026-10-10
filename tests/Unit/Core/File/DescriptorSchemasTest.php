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

namespace Thelia\Tests\Unit\Core\File;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\Finder;

/**
 * The schemas of the descriptors are handed to libxml as bytes, with no base to resolve
 * a relative reference against (XmlDescriptor): a schema of the core includes, imports
 * or redefines nothing, or the reference would be looked for in the working directory.
 */
final class DescriptorSchemasTest extends TestCase
{
    public function testNoSchemaOfTheCoreRefersToAnother(): void
    {
        $core = \dirname(__DIR__, 4).'/core/lib/Thelia';
        $schemas = iterator_to_array(Finder::create()->files()->name('*.xsd')->in([$core.'/Module/schema', $core.'/Core/Template/Validator/schema']), false);

        self::assertGreaterThanOrEqual(4, \count($schemas));

        foreach ($schemas as $schema) {
            self::assertDoesNotMatchRegularExpression('/<(?:\w+:)?(?:include|import|redefine)\b/', $schema->getContents(), $schema->getPathname());
        }
    }
}
