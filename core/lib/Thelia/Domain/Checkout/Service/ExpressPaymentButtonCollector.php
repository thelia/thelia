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

use Propel\Runtime\ActiveQuery\Criteria;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Payment\IsValidPaymentEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Checkout\DTO\ExpressPaymentButton;
use Thelia\Domain\Checkout\Enum\ExpressPaymentZone;
use Thelia\Model\Cart;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;
use Thelia\Module\BaseModule;
use Thelia\Module\ExpressPaymentModuleInterface;

/**
 * The express payment buttons a shop shows for one cart, in one place of the shop.
 *
 * Everything asks here — the theme, the API, a decoupled front — so a shop has one answer
 * to give and two modules offering express payment are ordered rather than fighting over
 * the same corner of the page.
 *
 * The cart is an argument, never read from the session: this also answers requests that
 * carry no session at all. It is on the path of every checkout page, so it stays at the
 * cost of the checkout's own payment list: a module row query, then whatever each module
 * decides from what it already holds. A module that calls its provider to decide whether
 * to show a button is a module that slows down the checkout.
 */
final readonly class ExpressPaymentButtonCollector
{
    public const ZONES_CONFIG_NAME = 'express_payment_zones';

    private LoggerInterface $logger;

    public function __construct(
        private ContainerInterface $container,
        private EventDispatcherInterface $dispatcher,
        private ExpressCheckoutConfirmationToken $confirmationToken,
        private UrlGeneratorInterface $urlGenerator,
        ?LoggerInterface $logger = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * @return list<ExpressPaymentButton>
     */
    public function collect(Cart $cart, ExpressPaymentZone $zone): array
    {
        if (!$this->isZoneEnabled($zone)) {
            return [];
        }

        // An empty cart has nothing to pay for, and a wallet opened over one can only end
        // in a refusal the buyer did not ask for.
        if (0 === $cart->countCartItems()) {
            return [];
        }

        $buttons = [];

        foreach ($this->activePaymentModules() as $module) {
            $button = $this->buttonOf($module, $cart, $zone);

            if (null !== $button) {
                $buttons[] = $button;
            }
        }

        return $buttons;
    }

    /** Whether the merchant shows express payment in this place at all. */
    public function isZoneEnabled(ExpressPaymentZone $zone): bool
    {
        return \in_array(
            $zone,
            ExpressPaymentZone::listFromStoredValue(ConfigQuery::read(self::ZONES_CONFIG_NAME, '')),
            true
        );
    }

    private function buttonOf(Module $module, Cart $cart, ExpressPaymentZone $zone): ?ExpressPaymentButton
    {
        $instance = $module->getPaymentModuleInstance($this->container);

        if (!$instance instanceof ExpressPaymentModuleInterface) {
            return null;
        }

        if (!\in_array($zone, $instance->expressPaymentZones(), true)) {
            return null;
        }

        // The same judge as the payment options offered at checkout, so a module refused
        // there — amount out of range, currency it will not take — is not offered a
        // shortcut around it here.
        $isValid = new IsValidPaymentEvent($instance, $cart);
        $this->dispatcher->dispatch($isValid, TheliaEvents::MODULE_PAYMENT_IS_VALID);

        if (!$isValid->isValidModule()) {
            return null;
        }

        $button = $instance->expressPaymentButton($cart, $zone);

        if (null === $button) {
            return null;
        }

        // A module that names another module's id would have the front post its
        // confirmation to the wrong place.
        if ($button->paymentModuleId !== (int) $module->getId()) {
            $this->logger->warning('The module "{code}" offered an express payment button belonging to module {claimed}, and it was dropped.', [
                'code' => (string) $module->getCode(),
                'claimed' => $button->paymentModuleId,
            ]);

            return null;
        }

        $routeParameters = ['moduleCode' => (string) $module->getCode()];

        return $button->withConfirmation(
            $this->urlGenerator->generate('express_checkout_confirm', $routeParameters, UrlGeneratorInterface::ABSOLUTE_PATH),
            $this->urlGenerator->generate('express_checkout_amount', $routeParameters, UrlGeneratorInterface::ABSOLUTE_PATH),
            $this->confirmationToken->issueFor($cart, (int) $module->getId()),
        );
    }

    /**
     * @return list<Module>
     */
    private function activePaymentModules(): array
    {
        return array_values(ModuleQuery::create()
            ->filterByActivate(1)
            ->filterByType(BaseModule::PAYMENT_MODULE_TYPE, Criteria::EQUAL)
            ->orderByPosition()
            ->find()
            ->getData());
    }
}
