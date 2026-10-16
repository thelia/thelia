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
use Thelia\Core\Event\Checkout\ExpressPaymentNotPlacedEvent;
use Thelia\Domain\Checkout\DTO\ExpressPaymentButton;
use Thelia\Domain\Checkout\DTO\ExpressWalletAnswer;
use Thelia\Domain\Checkout\Exception\ExpressCheckoutConfirmationDeniedException;
use Thelia\Domain\Checkout\Exception\ExpressCheckoutRefusedException;
use Thelia\Model\Cart;

/**
 * What the shop hands a module and a script about an express payment: the button with the
 * shop's routes, the report of a payment not placed, and the refusals the script shows.
 */
final class ExpressPaymentDescriptionsTest extends TestCase
{
    public function testTheShopsRoutesAreAddedToTheButtonTheModuleDescribed(): void
    {
        $button = new ExpressPaymentButton(7, 'Wallet', 'wallet', 'Pay with the wallet', '/logo.svg', ['data-zone' => 'checkout']);

        $confirmed = $button->withConfirmation('/confirm', '/amount', 'token');

        self::assertSame([7, 'Wallet', 'wallet', 'Pay with the wallet', '/logo.svg', ['data-zone' => 'checkout']], [
            $confirmed->paymentModuleId, $confirmed->paymentModuleCode, $confirmed->code, $confirmed->label, $confirmed->logo, $confirmed->attributes,
        ]);
        self::assertSame(['/confirm', '/amount', 'token'], [$confirmed->confirmationUrl, $confirmed->amountUrl, $confirmed->confirmationToken]);
        self::assertNull($button->confirmationUrl, 'The button the module described is left as it was.');
    }

    public function testAPlacementThatFailedIsReportedWithItsReason(): void
    {
        $cart = new Cart();
        $answer = new ExpressWalletAnswer(12.5);
        $reason = new \RuntimeException('Out of stock.');

        $event = new ExpressPaymentNotPlacedEvent('Wallet', $cart, $answer, $reason);

        self::assertSame('Wallet', $event->getPaymentModuleCode());
        self::assertSame($cart, $event->getCart());
        self::assertSame($answer, $event->getAnswer());
        self::assertSame($reason, $event->getReason());
        self::assertFalse($event->wasAlreadyPlaced());
    }

    public function testACartAlreadyOrderedIsReportedWithoutAReason(): void
    {
        $event = new ExpressPaymentNotPlacedEvent('Wallet', new Cart(), new ExpressWalletAnswer(12.5), null);

        self::assertNull($event->getReason());
        self::assertTrue($event->wasAlreadyPlaced());
    }

    /** Both totals to the cent, the way the buyer reads them. */
    public function testATotalThatDiffersNamesBothAmounts(): void
    {
        $message = ExpressCheckoutRefusedException::becauseTheTotalDoesNotMatch(12.5, 13.456)->getMessage();

        self::assertStringContainsString('12.50', $message);
        self::assertStringContainsString('13.46', $message);
    }

    public function testEachRefusalSaysWhatIsMissing(): void
    {
        self::assertStringContainsString('belongs to nobody', ExpressCheckoutRefusedException::becauseTheCartCarriesNoCustomer()->getMessage());
        self::assertStringContainsString('no cart', ExpressCheckoutRefusedException::becauseThereIsNoCartToPayFor()->getMessage());
        self::assertStringContainsString('delivery method', ExpressCheckoutRefusedException::becauseNoDeliveryIsChosenYet()->getMessage());
        self::assertStringContainsString('identified', ExpressCheckoutRefusedException::becauseTheBuyerIsNotIdentified()->getMessage());
    }

    public function testADeniedConfirmationNamesTheModule(): void
    {
        self::assertStringContainsString('"Cheque"', ExpressCheckoutConfirmationDeniedException::becauseTheModuleOffersNoExpressPayment('Cheque')->getMessage());
        self::assertStringContainsString('not issued for the cart', ExpressCheckoutConfirmationDeniedException::becauseTheTokenIsNotForThisCart()->getMessage());
    }
}
