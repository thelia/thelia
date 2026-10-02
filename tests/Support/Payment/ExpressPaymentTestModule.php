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

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Checkout\DTO\ExpressPaymentButton;
use Thelia\Domain\Checkout\DTO\ExpressWalletAnswer;
use Thelia\Domain\Checkout\Enum\ExpressPaymentZone;
use Thelia\Domain\Checkout\Exception\ExpressCheckoutRefusedException;
use Thelia\Model\Cart;
use Thelia\Model\Order;
use Thelia\Model\OrderStatusQuery;
use Thelia\Module\AbstractPaymentModule;
use Thelia\Module\ExpressPaymentModuleInterface;

/**
 * A payment module that offers a wallet button, without a wallet behind it.
 *
 * It answers from what a test hands it: which zones it fills, which module id it claims,
 * and whether it wants to show anything at all. That is enough to prove what the
 * collector promises — the shop's setting decides where buttons may appear, the module
 * decides whether one does.
 *
 * The code of a module is the short name of its class and nothing else
 * (BaseModule::getCode() calls self::getModuleCode(), which no override ever reaches),
 * so the row a test writes has to carry this very name.
 */
final class ExpressPaymentTestModule extends AbstractPaymentModule implements ExpressPaymentModuleInterface
{
    public const MODULE_CODE = 'ExpressPaymentTestModule';

    /** @var list<ExpressPaymentZone> */
    public static array $zones = [ExpressPaymentZone::Checkout];

    public static ?int $claimedModuleId = null;

    public static bool $offersButton = true;

    public static bool $accepts = true;

    /** What the provider vouches for; null plays a provider that refuses the payment. */
    public static ?ExpressWalletAnswer $answer = null;

    /** Whether the module is also a payment method of the checkout, like a card form would be. */
    public static bool $alsoOfferedAtCheckout = false;

    /** Whether the wallet takes the money as the buyer confirms, as Google Pay or Apple Pay do. */
    public static bool $paysOnTheSpot = false;

    /** The cart the shop handed over at the last confirmation, to check which one it was. */
    public static ?int $cartIdSeenAtConfirmation = null;

    public function pay(Order $order): ?Response
    {
        if (!self::$paysOnTheSpot) {
            return null;
        }

        $event = new OrderEvent($order);
        $event->setStatus(OrderStatusQuery::getPaidStatus()->getId());
        $this->getDispatcher()->dispatch($event, TheliaEvents::ORDER_UPDATE_STATUS);

        return new Response('');
    }

    public function isValidPayment(): bool
    {
        return self::$accepts;
    }

    public function isAlsoOfferedAtCheckout(): bool
    {
        return self::$alsoOfferedAtCheckout;
    }

    public function expressPaymentZones(): array
    {
        return self::$zones;
    }

    public function expressPaymentButton(Cart $cart, ExpressPaymentZone $zone): ?ExpressPaymentButton
    {
        if (!self::$offersButton) {
            return null;
        }

        return new ExpressPaymentButton(
            (int) self::$claimedModuleId,
            self::MODULE_CODE,
            'wallet',
            'Pay with the test wallet',
            null,
            ['data-zone' => $zone->value],
        );
    }

    public function readExpressPaymentConfirmation(Request $request, Cart $cart): ExpressWalletAnswer
    {
        self::$cartIdSeenAtConfirmation = (int) $cart->getId();

        return self::$answer ?? throw new ExpressCheckoutRefusedException('The provider does not vouch for this payment.');
    }

    /** Back to what a fresh test expects, since the settings live on the class. */
    public static function reset(int $moduleId): void
    {
        self::$zones = [ExpressPaymentZone::Checkout];
        self::$claimedModuleId = $moduleId;
        self::$offersButton = true;
        self::$accepts = true;
        self::$answer = null;
        self::$cartIdSeenAtConfirmation = null;
        self::$paysOnTheSpot = false;
        self::$alsoOfferedAtCheckout = false;
    }
}
