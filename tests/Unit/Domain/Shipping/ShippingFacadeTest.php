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

namespace Thelia\Tests\Unit\Domain\Shipping;

use PHPUnit\Framework\TestCase;
use Thelia\Domain\Shipping\DTO\PostageEstimateView;

class ShippingFacadeTest extends TestCase
{
    public function testPostageEstimateViewWithValues(): void
    {
        $view = new PostageEstimateView(
            amountHt: 10.0,
            tax: 2.0,
            totalTtc: 12.0,
        );

        $this->assertSame(10.0, $view->amountHt);
        $this->assertSame(2.0, $view->tax);
        $this->assertSame(12.0, $view->totalTtc);
    }

    public function testPostageEstimateViewWithNullValues(): void
    {
        $view = new PostageEstimateView(
            amountHt: null,
            tax: null,
            totalTtc: null,
        );

        $this->assertNull($view->amountHt);
        $this->assertNull($view->tax);
        $this->assertNull($view->totalTtc);
    }

    public function testPostageEstimateViewPartialValues(): void
    {
        $view = new PostageEstimateView(
            amountHt: 15.5,
            tax: null,
            totalTtc: null,
        );

        $this->assertSame(15.5, $view->amountHt);
        $this->assertNull($view->tax);
        $this->assertNull($view->totalTtc);
    }

    public function testPostageEstimateViewPropertiesAreReadonly(): void
    {
        $view = new PostageEstimateView(
            amountHt: 10.0,
            tax: 2.0,
            totalTtc: 12.0,
        );

        $reflection = new \ReflectionClass($view);

        $this->assertTrue($reflection->getProperty('amountHt')->isReadOnly());
        $this->assertTrue($reflection->getProperty('tax')->isReadOnly());
        $this->assertTrue($reflection->getProperty('totalTtc')->isReadOnly());
    }
}
