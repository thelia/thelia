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

namespace Thelia\Domain\Payment;

use Propel\Runtime\Exception\PropelException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Thelia\Domain\Payment\DTO\InstantShare;
use Thelia\Domain\Payment\DTO\PaymentComposition;
use Thelia\Domain\Payment\Exception\PaymentCompositionRefusedException;
use Thelia\Log\Tlog;
use Thelia\Model\Cart;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;
use Thelia\Model\Order;
use Thelia\Module\BalanceChargingPaymentModuleInterface;
use Thelia\Module\BaseModule;
use Thelia\Module\InstantPaymentModuleInterface;

/**
 * Reads what the instant payment modules applied to a cart and has the splitter make one
 * payment of it.
 *
 * Asked when an order is placed, and nowhere else: the steps of the tunnel are read by
 * every page that shows the progression, and a composition is not something a cart is
 * missing. Twice per placement, on purpose: before anything is written, on the total the
 * cart shows, so that a composition the core refuses costs the buyer neither an order nor
 * the cancellation of the previous unpaid one; and again inside the transaction that
 * writes the order, on the total of the order itself, which is the one the parts are
 * written from.
 */
final readonly class OrderPaymentComposer
{
    public function __construct(
        private OrderPaymentSplitter $splitter,
        private ContainerInterface $container,
    ) {
    }

    /**
     * The composition the cart would be paid with, before its order exists.
     *
     * The total is the one the cart shows the buyer — goods, discount, postage and gift
     * wrapping, taxed for the country it is delivered to — which is what the order is
     * written with, give or take the rounding mode the order is totalled under.
     *
     * @throws PaymentCompositionRefusedException
     */
    public function composeForCart(Cart $cart, int $paymentModuleId): PaymentComposition
    {
        return $this->compose($cart, fn (): string => $this->amount($this->totalTheCartShows($cart)), $paymentModuleId);
    }

    /**
     * The composition written on an order, from its own total.
     *
     * @throws PaymentCompositionRefusedException
     */
    public function composeForOrder(Cart $cart, Order $order, ?int $paymentModuleId): PaymentComposition
    {
        return $this->compose($cart, fn (): string => $this->amount($order->getTotalAmount()), $paymentModuleId);
    }

    /**
     * The total is read only when an instant module applied something: a cart paid with
     * one means, which is nearly every cart, costs the tax computation nothing. The balance
     * of the empty composition is not computed, and nothing reads it.
     *
     * @param \Closure(): string $orderTotal
     *
     * @throws PaymentCompositionRefusedException
     */
    private function compose(Cart $cart, \Closure $orderTotal, ?int $paymentModuleId): PaymentComposition
    {
        $shares = [];
        $chosenModule = null;
        $chosenIsInstant = false;

        foreach ($this->activePaymentModules() as $module) {
            $instance = $this->instanceOf($module);

            if ($module->getId() === $paymentModuleId) {
                $chosenModule = $instance;
                $chosenIsInstant = $instance instanceof InstantPaymentModuleInterface;
            }

            if (!$instance instanceof InstantPaymentModuleInterface) {
                continue;
            }

            $applied = $this->decimalAmount($this->amountAppliedBy($instance, $cart, (int) $module->getId()), (int) $module->getId());

            // Nothing applied is not a share: the splitter would drop it, and the total
            // is then never asked for.
            if (null !== $applied && (float) $applied > 0) {
                $shares[] = new InstantShare((int) $module->getId(), $applied);
            }
        }

        if ([] === $shares) {
            return new PaymentComposition([], $this->amount(0.0));
        }

        // The module chosen on the cart that is itself an instant means is one of the
        // parts, and there is no provider left behind it.
        return $this->splitter->split(
            $orderTotal(),
            $shares,
            $chosenIsInstant ? null : $paymentModuleId,
            $chosenModule instanceof BalanceChargingPaymentModuleInterface,
        );
    }

    /**
     * @return iterable<Module>
     */
    private function activePaymentModules(): iterable
    {
        return ModuleQuery::create()
            ->filterByActivate(BaseModule::IS_ACTIVATED)
            ->filterByType(BaseModule::PAYMENT_MODULE_TYPE)
            ->orderByPosition()
            ->find();
    }

    /**
     * A module that cannot be instantiated is one that is not part of the application any
     * more (a module row whose code is gone): it applies nothing, and does not stop the
     * others from being paid with.
     */
    private function instanceOf(Module $module): ?object
    {
        try {
            return $module->getPaymentModuleInstance($this->container);
        } catch (\InvalidArgumentException) {
            Tlog::getInstance()->warning(\sprintf('Payment module %d is active but cannot be instantiated: it applies nothing to the cart.', (int) $module->getId()));

            return null;
        }
    }

    /**
     * What the module says it applied, or "0" when it cannot say: one module whose own
     * service is down applies nothing, and neither stops the other means nor every cart of
     * the shop. Logged by module id and class of the failure, without what it said.
     */
    private function amountAppliedBy(InstantPaymentModuleInterface $instance, Cart $cart, int $moduleId): string
    {
        try {
            return $instance->getAmountAppliedToCart($cart);
        } catch (\Throwable $throwable) {
            Tlog::getInstance()->error(\sprintf('Payment module %d could not tell the amount it applied to the cart (%s): it applies nothing.', $moduleId, $throwable::class));

            return '0';
        }
    }

    /**
     * What a module answers is a decimal string by contract, but a float cast to a string
     * comes out as "1.0E-5": a numeric answer is written as a plain decimal, and an answer
     * that is no number is refused, which counts as an amount of nothing.
     */
    private function decimalAmount(string $answer, int $moduleId): ?string
    {
        $answer = trim($answer);

        if (1 === preg_match('/^\d+(?:\.\d+)?$/', $answer)) {
            return $answer;
        }

        if (!is_numeric($answer) || !is_finite((float) $answer)) {
            Tlog::getInstance()->warning(\sprintf('Payment module %d answered an amount that is not a number: ignored.', $moduleId));

            return null;
        }

        return $this->amount((float) $answer);
    }

    /**
     * @throws PropelException
     */
    private function totalTheCartShows(Cart $cart): float
    {
        [$country, $state] = BaseModule::resolveTaxationPlace($cart, $this->container);

        return $cart->getTaxedAmount($country, true, $state, true, true);
    }

    private function amount(float $total): string
    {
        return number_format($total, 6, '.', '');
    }
}
