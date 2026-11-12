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

namespace Thelia\Tests\Unit\Domain\Pricing\PriceVisibility;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Thelia\Domain\Pricing\PriceVisibility\PriceDisplayMode;
use Thelia\Model\Customer;

/**
 * Who sees the prices and who may buy, per mode: three modes against a visitor and a customer.
 */
final class PriceDisplayModeTest extends TestCase
{
    /**
     * @return iterable<string, array{PriceDisplayMode, bool, bool}>
     */
    public static function modes(): iterable
    {
        yield 'visible' => [PriceDisplayMode::Visible, true, true];
        yield 'logged in' => [PriceDisplayMode::LoggedIn, false, true];
        yield 'on request' => [PriceDisplayMode::OnRequest, false, false];
    }

    #[DataProvider('modes')]
    public function testWhatAVisitorAndACustomerAreShown(PriceDisplayMode $mode, bool $visitorSees, bool $customerSees): void
    {
        self::assertSame($visitorSees, $mode->showsPrice(null));
        self::assertSame($customerSees, $mode->showsPrice(new Customer()));
    }

    #[DataProvider('modes')]
    public function testNobodyBuysWhatTheyAreNotShownThePriceOf(PriceDisplayMode $mode, bool $visitorSees, bool $customerSees): void
    {
        self::assertSame($visitorSees, $mode->purchasable(null));
        self::assertSame($customerSees, $mode->purchasable(new Customer()));
    }

    public function testAProductFollowsTheShopWhenItStatesNothingOrAnUnknownMode(): void
    {
        self::assertNull(PriceDisplayMode::fromProductValue(null));
        self::assertNull(PriceDisplayMode::fromProductValue(''));
        self::assertNull(PriceDisplayMode::fromProductValue('sometimes'));
        self::assertSame(PriceDisplayMode::OnRequest, PriceDisplayMode::fromProductValue('on_request'));
    }
}
