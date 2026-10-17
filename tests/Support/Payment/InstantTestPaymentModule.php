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

use Symfony\Component\HttpFoundation\Response;
use Thelia\Model\Cart;
use Thelia\Model\Order;
use Thelia\Model\OrderPaymentPart;
use Thelia\Module\AbstractPaymentModule;
use Thelia\Module\InstantPaymentModuleInterface;

/**
 * A payment module that settles part of an order at once, the way a gift card does.
 *
 * Registered under a `module` row and as a `module.<code>` service by
 * UsesInstantPaymentModules. What the test sets, and what the core asked, lives in static
 * state, which the test resets: the amount the module says it applied to any cart, the
 * parts it was asked to settle and to release, and what it answers when asked to settle.
 */
class InstantTestPaymentModule extends AbstractPaymentModule implements InstantPaymentModuleInterface
{
    /**
     * Decimal string the module applies to every cart it is asked about.
     */
    public static string $appliedAmount = '0';

    /**
     * @var list<int> the carts the core asked about, in order
     */
    public static array $askedAboutCartIds = [];

    /**
     * @var list<int>
     */
    public static array $settledPartIds = [];

    /**
     * @var list<int>
     */
    public static array $releasedPartIds = [];

    /**
     * The part ids whose settlement the module refuses, by throwing.
     *
     * @var list<int>
     */
    public static array $refusedPartIds = [];

    /**
     * What the core asked the module, in order: "pay", "settle" and "release".
     *
     * @var list<string>
     */
    public static array $calls = [];

    /**
     * Whether the module refuses to settle any part, by throwing. SecondInstantTestPaymentModule
     * reads its own flag, so that one of two instant modules can refuse while the other settles.
     */
    public static bool $refusesEveryPart = false;

    /**
     * The part ids whose release the module fails, by throwing.
     *
     * @var list<int>
     */
    public static array $unreleasablePartIds = [];

    /**
     * Run when the module is asked to settle or to release a part, before it answers: what
     * a concurrency test does while the core holds its locks.
     */
    public static ?\Closure $whenAskedToSettle = null;

    public static ?\Closure $whenAskedToRelease = null;

    /**
     * Whether the module throws when asked the amount it applied, the way a module whose
     * own service is down does. SecondInstantTestPaymentModule answers on its own.
     */
    public static bool $failsWhenAskedForTheAmount = false;

    public static function reset(): void
    {
        self::$failsWhenAskedForTheAmount = false;
        self::$refusesEveryPart = false;
        self::$unreleasablePartIds = [];
        self::$whenAskedToSettle = null;
        self::$whenAskedToRelease = null;
        self::$appliedAmount = '0';
        self::$askedAboutCartIds = [];
        self::$settledPartIds = [];
        self::$releasedPartIds = [];
        self::$refusedPartIds = [];
        self::$calls = [];
    }

    public function getAmountAppliedToCart(Cart $cart): string
    {
        self::$askedAboutCartIds[] = (int) $cart->getId();

        if (self::$failsWhenAskedForTheAmount) {
            throw new \RuntimeException('The test module cannot tell the amount it applied.');
        }

        return self::$appliedAmount;
    }

    public function settlePart(OrderPaymentPart $part): void
    {
        self::$calls[] = 'settle';

        if (null !== self::$whenAskedToSettle) {
            (self::$whenAskedToSettle)($part);
        }

        if (static::refusesEveryPart() || \in_array((int) $part->getId(), self::$refusedPartIds, true)) {
            throw new \RuntimeException('The test module refuses to settle this part.');
        }

        self::$settledPartIds[] = (int) $part->getId();
    }

    protected static function refusesEveryPart(): bool
    {
        return self::$refusesEveryPart;
    }

    public function releasePart(OrderPaymentPart $part): void
    {
        self::$calls[] = 'release';

        if (null !== self::$whenAskedToRelease) {
            (self::$whenAskedToRelease)($part);
        }

        if (\in_array((int) $part->getId(), self::$unreleasablePartIds, true)) {
            throw new \RuntimeException('The test module cannot release this part.');
        }

        self::$releasedPartIds[] = (int) $part->getId();
    }

    public function pay(Order $order): ?Response
    {
        self::$calls[] = 'pay';

        return null;
    }

    public function isValidPayment(): bool
    {
        return true;
    }

    public function supportsPaymentRetry(): bool
    {
        return true;
    }
}
