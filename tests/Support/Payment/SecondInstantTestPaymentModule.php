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

namespace Thelia\Tests\Support\Payment;

use Thelia\Model\Cart;

/**
 * A second instant payment module, for the tests that need two of them in one order: the
 * first settles while this one refuses, say. It shares the call log and the settled and
 * released part ids of InstantTestPaymentModule, which is what lets a test read the order
 * in which the core called them; the amount it applies and its refusal are its own.
 */
final class SecondInstantTestPaymentModule extends InstantTestPaymentModule
{
    public static string $secondAppliedAmount = '0';

    public static bool $secondRefusesEveryPart = false;

    public static function reset(): void
    {
        self::$secondAppliedAmount = '0';
        self::$secondRefusesEveryPart = false;
    }

    public function getAmountAppliedToCart(Cart $cart): string
    {
        return self::$secondAppliedAmount;
    }

    protected static function refusesEveryPart(): bool
    {
        return self::$secondRefusesEveryPart;
    }
}
