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

namespace Thelia\Tests\Integration\Quote\Cart;

use Symfony\Contracts\EventDispatcher\Event;
use Thelia\Action\Coupon as CouponAction;
use Thelia\Core\Event\Delivery\DeliveryPostageEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Domain\Cart\CartFacade;
use Thelia\Domain\Checkout\DTO\CheckoutDTO;
use Thelia\Domain\Quote\Cart\QuoteCartPostageSubscriber;
use Thelia\Domain\Shipping\Service\PostageHandler;
use Thelia\Model\Coupon;
use Thelia\Model\ModuleQuery;
use Thelia\Model\QuoteRevision;

/**
 * The postage of the cart of a quote is the postage of the accepted revision, 15 without taxes
 * and so 18 with the 20 % VAT, whatever the carrier would quote, a coupon would remove, or the
 * buyer does in the checkout.
 */
final class QuoteCartPostageTest extends QuoteCartTestCase
{
    /** @var int how many times the carrier was asked for a price */
    private int $carrierWasAsked = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dispatcher->addListener(
            TheliaEvents::MODULE_DELIVERY_GET_POSTAGE,
            function (DeliveryPostageEvent $event): void {
                ++$this->carrierWasAsked;
            },
            1024,
        );
    }

    public function testThePostageOfTheCartIsTheOneOfTheRevisionAndTheCarrierIsNotAsked(): void
    {
        $quoteCart = $this->quoteCart();

        self::assertSame(18.0, (float) $quoteCart->getPostage());
        self::assertSame(3.0, (float) $quoteCart->getPostageTax());
        self::assertSame(15.0, $quoteCart->getUntaxedPostage());
        self::assertSame(0, $this->carrierWasAsked);
    }

    public function testAnOrdinaryCartIsQuotedByTheCarrier(): void
    {
        $ordinaryCart = $this->ordinaryCart();

        $this->getService(PostageHandler::class)->handlePostageOnCart($ordinaryCart);

        self::assertSame(99.0, (float) $this->reloaded($ordinaryCart)->getPostage());
        self::assertSame(1, $this->carrierWasAsked);
    }

    public function testThePostageIsAskedOfTheRevisionTheCartNamesAndNotTheLastOne(): void
    {
        $quoteCart = $this->quoteCart(postage: '15.000000');
        // A later revision, which the buyer did not accept.
        (new QuoteRevision())->setQuoteId($this->quote->getId())->setNumber(3)->setAuthor('admin')->setState('sent')->setPostage('40.000000')
            ->save($this->getPropelConnection());
        $this->quote->setCurrentRevision(3)->save($this->getPropelConnection());

        $this->getService(PostageHandler::class)->handlePostageOnCart($quoteCart);

        self::assertSame(18.0, (float) $this->reloaded($quoteCart)->getPostage());
    }

    public function testACouponThatRemovesThePostageDoesNotRemoveTheOneOfAQuote(): void
    {
        $this->factory->coupon([
            'code' => null,
            'triggerMode' => Coupon::TRIGGER_MODE_AUTOMATIC,
            'type' => 'thelia.coupon.type.remove_x_percent',
            'effects' => ['percentage' => 10.0],
            'conditions' => $this->atLeastOneLine(),
            'isRemovingPostage' => true,
            'isCumulative' => true,
        ]);

        $ordinaryCart = $this->ordinaryCart();
        $this->session()->set(Session::SESSION_CART_ID_NAME, $ordinaryCart->getId());
        $this->getService(PostageHandler::class)->handlePostageOnCart($ordinaryCart);
        self::assertSame(0.0, (float) $this->reloaded($ordinaryCart)->getPostage(), 'the coupon takes the postage off an ordinary cart');

        $quoteCart = $this->quoteCart();
        $this->getService(PostageHandler::class)->handlePostageOnCart($quoteCart);

        self::assertSame(18.0, (float) $this->reloaded($quoteCart)->getPostage());
    }

    public function testTheCouponActionLeavesThePostageOfAQuoteWhateverTheEvent(): void
    {
        $this->factory->coupon([
            'code' => null,
            'triggerMode' => Coupon::TRIGGER_MODE_AUTOMATIC,
            'type' => 'thelia.coupon.type.remove_x_percent',
            'effects' => ['percentage' => 10.0],
            'conditions' => $this->atLeastOneLine(),
            'isRemovingPostage' => true,
            'isCumulative' => true,
        ]);
        $quoteCart = $this->quoteCart();

        // Called as the legacy event of the order does, which the postage listener of the quote does not stand before.
        $this->getService(CouponAction::class)->forceFreePostage(new Event());

        self::assertSame(18.0, (float) $this->reloaded($quoteCart)->getPostage());
    }

    public function testTheSelectionsOfTheDeliveryAndPaymentStepsLeaveThePostageOfAQuote(): void
    {
        $quoteCart = $this->quoteCart();
        $facade = $this->getService(CartFacade::class);
        $cheque = ModuleQuery::create()->findOneByCode('Cheque');
        $custom = ModuleQuery::create()->findOneByCode('CustomDelivery');

        $facade->setDeliveryAddress(new CheckoutDTO($quoteCart));
        self::assertSame(18.0, (float) $this->reloaded($quoteCart)->getPostage(), 'delivery address');

        $facade->setDeliveryModule(new CheckoutDTO($this->reloaded($quoteCart), deliveryModuleId: $custom?->getId()));
        self::assertSame(18.0, (float) $this->reloaded($quoteCart)->getPostage(), 'delivery module');

        $facade->setPaymentModule(new CheckoutDTO($this->reloaded($quoteCart), paymentModuleId: $cheque?->getId()));
        $quoteCart = $this->reloaded($quoteCart);
        self::assertSame(18.0, (float) $quoteCart->getPostage(), 'payment module');
        self::assertSame(3.0, (float) $quoteCart->getPostageTax());
        self::assertSame($cheque?->getId(), $quoteCart->getPaymentModuleId());
        self::assertSame(0, $this->carrierWasAsked);
    }

    public function testTheListenerRunsBeforeTheCarrierAndTheCouponThatRemovesThePostage(): void
    {
        $priority = QuoteCartPostageSubscriber::getSubscribedEvents()[TheliaEvents::CART_SET_POSTAGE][1];

        // The carrier is 128 (Action\Cart) and the coupon that removes the postage 132 (Action\Coupon).
        self::assertGreaterThan(132, $priority);
    }
}
