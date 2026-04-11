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

namespace Thelia\Tests\Unit\Taxation;

use PHPUnit\Framework\TestCase;
use Thelia\Domain\Taxation\TaxEngine\TaxTypeRequirementDefinition;
use Thelia\Type\FloatType;

final class TaxTypeRequirementDefinitionTest extends TestCase
{
    public function testExposesNameTypeAndTitle(): void
    {
        $type = new FloatType();
        $definition = new TaxTypeRequirementDefinition('percent', $type, 'Percentage');

        self::assertSame('percent', $definition->getName());
        self::assertSame($type, $definition->getType());
        self::assertSame('Percentage', $definition->getTitle());
    }

    public function testTitleDefaultsToName(): void
    {
        $definition = new TaxTypeRequirementDefinition('amount', new FloatType());

        self::assertSame('amount', $definition->getTitle());
    }

    public function testIsValueValidDelegatesToTheUnderlyingType(): void
    {
        $definition = new TaxTypeRequirementDefinition('percent', new FloatType());

        self::assertTrue($definition->isValueValid('5.5'));
        self::assertTrue($definition->isValueValid(0));
        self::assertFalse($definition->isValueValid('not-a-number'));
    }
}
