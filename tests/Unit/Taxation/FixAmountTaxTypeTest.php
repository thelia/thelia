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
use Thelia\Domain\Taxation\TaxEngine\TaxType\FixAmountTaxType;
use Thelia\Model\Product;

final class FixAmountTaxTypeTest extends TestCase
{
    public function testSetAmountStoresRequirement(): void
    {
        $type = (new FixAmountTaxType())->setAmount(5);

        self::assertSame(5, $type->getRequirement('amount'));
    }

    public function testFixAmountRetrieverCastsRequirementToFloat(): void
    {
        $type = (new FixAmountTaxType())->setAmount('12.34');

        self::assertSame(12.34, $type->fixAmountRetriever(new Product()));
    }

    public function testPricePercentRetrieverReturnsZeroForFixAmountTypes(): void
    {
        $type = (new FixAmountTaxType())->setAmount(5);

        self::assertSame(0.0, $type->pricePercentRetriever());
    }

    public function testCalculateAddsFixAmountRegardlessOfUntaxedPrice(): void
    {
        $type = (new FixAmountTaxType())->setAmount(7.5);

        self::assertSame(7.5, $type->calculate(new Product(), 0.0));
        self::assertSame(7.5, $type->calculate(new Product(), 100.0));
        self::assertSame(7.5, $type->calculate(new Product(), 9999.99));
    }
}
