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

namespace Thelia\Tests\Unit\Domain\Checkout;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Thelia\Domain\Checkout\DTO\ExpressPaymentButton;
use Thelia\Domain\Checkout\DTO\ExpressWalletAnswer;
use Thelia\Domain\Checkout\Enum\ExpressPaymentZone;
use Thelia\Domain\Checkout\Service\CheckoutPaymentOffer;
use Thelia\Model\Cart;
use Thelia\Module\ExpressPaymentModuleInterface;

/**
 * The checkout places an order itself with any payment module but a wallet that only takes
 * express payment: that one is paid by its own button, or the order would be marked paid
 * with no money taken.
 */
final class CheckoutPaymentOfferTest extends TestCase
{
    public function testAModuleWrittenBeforeExpressPaymentIsPaidAtCheckout(): void
    {
        self::assertTrue(CheckoutPaymentOffer::canBePaidAtCheckout(new \stdClass()));
    }

    public function testAWalletThatOnlyTakesExpressPaymentIsNotPaidAtCheckout(): void
    {
        self::assertFalse(CheckoutPaymentOffer::canBePaidAtCheckout($this->wallet(alsoOfferedAtCheckout: false)));
    }

    public function testAModuleThatIsAlsoACardFormIsPaidAtCheckout(): void
    {
        self::assertTrue(CheckoutPaymentOffer::canBePaidAtCheckout($this->wallet(alsoOfferedAtCheckout: true)));
    }

    private function wallet(bool $alsoOfferedAtCheckout): ExpressPaymentModuleInterface
    {
        return new class($alsoOfferedAtCheckout) implements ExpressPaymentModuleInterface {
            public function __construct(private readonly bool $alsoOfferedAtCheckout)
            {
            }

            public function expressPaymentZones(): array
            {
                return [ExpressPaymentZone::Checkout];
            }

            public function isAlsoOfferedAtCheckout(): bool
            {
                return $this->alsoOfferedAtCheckout;
            }

            public function expressPaymentButton(Cart $cart, ExpressPaymentZone $zone): ?ExpressPaymentButton
            {
                return null;
            }

            public function readExpressPaymentConfirmation(Request $request, Cart $cart): ExpressWalletAnswer
            {
                return new ExpressWalletAnswer(0.0);
            }
        };
    }
}
