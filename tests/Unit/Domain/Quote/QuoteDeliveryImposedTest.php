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

namespace Thelia\Tests\Unit\Domain\Quote;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Translation\Translator;
use Thelia\Domain\Checkout\Enum\CheckoutViolationCode;
use Thelia\Domain\Checkout\Exception\InvalidDeliveryException;
use Thelia\Domain\Checkout\Exception\QuoteDeliveryImposedException;
use Thelia\Domain\Quote\Cart\QuoteCartDeliverySubscriber;

/**
 * The refusal of another carrier or another address on the cart of a quote: what a client branches
 * on, and where it stands among the listeners that write the choice.
 */
final class QuoteDeliveryImposedTest extends TestCase
{
    protected function setUp(): void
    {
        new Translator(new RequestStack());
    }

    public function testTheCarrierRefusalHasACodeOfItsOwnAndNamesTheField(): void
    {
        $refusal = QuoteDeliveryImposedException::aboutTheCarrier();

        self::assertSame('quote-delivery-imposed', $refusal->violationCode());
        self::assertSame(CheckoutViolationCode::QuoteDeliveryImposed->value, $refusal->violationCode());
        self::assertSame(['field' => 'deliveryModule'], $refusal->violationDetails());
    }

    public function testTheAddressRefusalHasTheSameCodeAndNamesTheOtherField(): void
    {
        $refusal = QuoteDeliveryImposedException::aboutTheAddress();

        self::assertSame('quote-delivery-imposed', $refusal->violationCode());
        self::assertSame(['field' => 'deliveryAddress'], $refusal->violationDetails());
    }

    public function testItIsARefusalOfTheDeliveryStepSoThatEveryCallerThatAnswersOneAnswersThisOne(): void
    {
        self::assertInstanceOf(InvalidDeliveryException::class, QuoteDeliveryImposedException::aboutTheCarrier());
    }

    public function testTheSubscriberStandsBeforeTheListenersThatWriteTheChoiceAndThoseThatReadIt(): void
    {
        $events = QuoteCartDeliverySubscriber::getSubscribedEvents();

        foreach ([TheliaEvents::CART_SET_DELIVERY_MODULE, TheliaEvents::CART_SET_DELIVERY_ADDRESS, TheliaEvents::CART_SET_DELIVERY_ADDRESS_MANUAL] as $event) {
            self::assertArrayHasKey($event, $events);
            // 128 writes the choice (Action\Cart), 10 reads it (Action\Coupon).
            self::assertGreaterThan(128, $events[$event][1], $event);
        }
    }
}
