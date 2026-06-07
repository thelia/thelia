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

namespace Thelia\Tests\Unit\Model;

use PHPUnit\Framework\TestCase;
use Thelia\Model\OrderPostage;

final class OrderPostageTest extends TestCase
{
    public function testConstructorAssignsItsArguments(): void
    {
        $postage = new OrderPostage(10.0, 2.0, 'TVA 20%');

        $this->assertSame(10.0, $postage->getAmount());
        $this->assertSame(2.0, $postage->getAmountTax());
        $this->assertSame('TVA 20%', $postage->getTaxRuleTitle());
        $this->assertSame(8.0, $postage->getUntaxedAmount());
    }

    public function testConstructorDefaultsToNull(): void
    {
        $postage = new OrderPostage();

        $this->assertNull($postage->getAmount());
        $this->assertNull($postage->getAmountTax());
        $this->assertNull($postage->getTaxRuleTitle());
    }

    public function testConstructorRoundsTheTaxAmount(): void
    {
        $postage = new OrderPostage(10.0, 1.999);

        $this->assertSame(2.0, $postage->getAmountTax());
    }

    public function testLoadFromPostageAcceptsAFloat(): void
    {
        $postage = OrderPostage::loadFromPostage(12.5);

        $this->assertSame(12.5, $postage->getAmount());
    }

    public function testLoadFromPostageReturnsTheSameInstance(): void
    {
        $original = new OrderPostage(5.0);

        $this->assertSame($original, OrderPostage::loadFromPostage($original));
    }

    public function testSetAmountTaxKeepsNull(): void
    {
        $postage = new OrderPostage();
        $postage->setAmountTax(null);

        $this->assertNull($postage->getAmountTax());
    }
}
