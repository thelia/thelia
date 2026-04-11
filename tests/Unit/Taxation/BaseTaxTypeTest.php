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
use Thelia\Domain\Taxation\TaxEngine\Exception\TaxEngineException;
use Thelia\Domain\Taxation\TaxEngine\TaxType\FixAmountTaxType;
use Thelia\Domain\Taxation\TaxEngine\TaxType\PricePercentTaxType;
use Thelia\Domain\Taxation\TaxEngine\TaxTypeRequirementDefinition;
use Thelia\Model\Product;
use Thelia\Type\FloatType;

/**
 * Tests the abstract base class via its two concrete price/percent and
 * fix-amount implementations. Everything below stays away from getTitle()
 * and getRequirementsDefinition() which require Translator initialization.
 */
final class BaseTaxTypeTest extends TestCase
{
    public function testSetRequirementStoresValue(): void
    {
        $type = new PricePercentTaxType();
        $type->setRequirement('custom', 'value');

        self::assertSame('value', $type->getRequirement('custom'));
    }

    public function testGetRequirementThrowsWhenMissing(): void
    {
        $this->expectException(TaxEngineException::class);
        $this->expectExceptionCode(TaxEngineException::UNDEFINED_REQUIREMENT_VALUE);

        (new PricePercentTaxType())->getRequirement('percent');
    }

    public function testGetRequirementsReturnsAllStoredPairs(): void
    {
        $type = (new PricePercentTaxType())->setPercentage(20);

        self::assertSame(['percent' => 20], $type->getRequirements());
    }

    public function testCalculateCombinesPricePercentAndFixAmount(): void
    {
        $type = new class extends FixAmountTaxType {
            public function pricePercentRetriever(): float
            {
                return 0.20;
            }

            public function fixAmountRetriever(Product $product): float
            {
                return 2.0;
            }
        };

        // 100 * 0.20 + 2 = 22
        self::assertEqualsWithDelta(22.0, $type->calculate(new Product(), 100.0), 1e-9);
    }

    public function testLoadRequirementsPopulatesFromDefinition(): void
    {
        $type = new class extends PricePercentTaxType {
            public function getRequirementsDefinition(): array
            {
                return [
                    new TaxTypeRequirementDefinition('percent', new FloatType()),
                ];
            }
        };

        $type->loadRequirements(['percent' => '5.5']);

        self::assertSame('5.5', $type->getRequirement('percent'));
    }

    public function testLoadRequirementsThrowsWhenKeyMissing(): void
    {
        $type = new class extends PricePercentTaxType {
            public function getRequirementsDefinition(): array
            {
                return [
                    new TaxTypeRequirementDefinition('percent', new FloatType()),
                ];
            }
        };

        $this->expectException(TaxEngineException::class);
        $this->expectExceptionCode(TaxEngineException::TAX_TYPE_REQUIREMENT_NOT_FOUND);

        $type->loadRequirements([]);
    }

    public function testLoadRequirementsThrowsWhenValueInvalid(): void
    {
        $type = new class extends PricePercentTaxType {
            public function getRequirementsDefinition(): array
            {
                return [
                    new TaxTypeRequirementDefinition('percent', new FloatType()),
                ];
            }
        };

        $this->expectException(TaxEngineException::class);
        $this->expectExceptionCode(TaxEngineException::TAX_TYPE_BAD_REQUIREMENT_VALUE);

        $type->loadRequirements(['percent' => 'not-a-float']);
    }
}
