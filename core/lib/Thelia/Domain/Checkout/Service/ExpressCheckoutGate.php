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

namespace Thelia\Domain\Checkout\Service;

use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Domain\Checkout\DTO\ExpressCheckoutAccess;
use Thelia\Domain\Checkout\Exception\ExpressCheckoutConfirmationDeniedException;
use Thelia\Domain\Checkout\Exception\ExpressCheckoutRefusedException;
use Thelia\Model\Cart;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;
use Thelia\Module\BaseModule;
use Thelia\Module\ExpressPaymentModuleInterface;

/**
 * The one check in front of every express route: an active module that takes express
 * payment, a cart in the session with something in it, and the token the shop issued
 * for that very pair.
 *
 * Nothing about the request is read before this has passed, so a page on another site
 * learns nothing, not even the amount of the cart, from a visitor's session.
 */
final readonly class ExpressCheckoutGate
{
    public function __construct(
        private ExpressCheckoutConfirmationToken $confirmationToken,
        private ContainerInterface $container,
        private EventDispatcherInterface $dispatcher,
    ) {
    }

    /**
     * @throws ExpressCheckoutConfirmationDeniedException when the request is not one the shop reads at all
     * @throws ExpressCheckoutRefusedException            when there is nothing to pay for
     */
    public function open(Request $request, string $moduleCode, string $token): ExpressCheckoutAccess
    {
        $session = $request->getSession();

        if (!$session instanceof Session) {
            throw ExpressCheckoutRefusedException::becauseThereIsNoCartToPayFor();
        }

        [$moduleId, $module] = $this->expressModule($moduleCode);

        $cart = $this->cartToPayFor($session);

        if (!$this->confirmationToken->isIssuedFor($token, $cart, $moduleId)) {
            throw ExpressCheckoutConfirmationDeniedException::becauseTheTokenIsNotForThisCart();
        }

        return new ExpressCheckoutAccess($cart, $moduleId, $module);
    }

    public function cartToPayFor(Session $session): Cart
    {
        $cart = $session->getSessionCart($this->dispatcher);

        if (0 === $cart->countCartItems()) {
            throw ExpressCheckoutRefusedException::becauseThereIsNoCartToPayFor();
        }

        return $cart;
    }

    /**
     * @return array{0: int, 1: ExpressPaymentModuleInterface}
     */
    private function expressModule(string $moduleCode): array
    {
        $row = ModuleQuery::create()
            ->filterByCode($moduleCode)
            ->filterByType(BaseModule::PAYMENT_MODULE_TYPE)
            ->filterByActivate(BaseModule::IS_ACTIVATED)
            ->findOne();

        $instance = $row instanceof Module ? $row->getPaymentModuleInstance($this->container) : null;

        if (!$instance instanceof ExpressPaymentModuleInterface) {
            throw ExpressCheckoutConfirmationDeniedException::becauseTheModuleOffersNoExpressPayment($moduleCode);
        }

        return [(int) $row->getId(), $instance];
    }
}
