<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Service\Model;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Exception\PropelException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Form\Form;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Thelia\Core\Event\Cart\CartEvent;
use Thelia\Core\Event\Delivery\DeliveryPostageEvent;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Coupon\CouponManager;
use Thelia\Coupon\Type\CouponInterface;
use Thelia\Form\Exception\FormValidationException;
use Thelia\Log\Tlog;
use Thelia\Model\AddressQuery;
use Thelia\Model\AreaDeliveryModuleQuery;
use Thelia\Model\Cart;
use Thelia\Model\Country;
use Thelia\Model\CouponCountry;
use Thelia\Model\CouponModule;
use Thelia\Model\ModuleQuery;
use Thelia\Model\State;
use Thelia\Module\BaseModule;
use Thelia\Module\Exception\DeliveryException;

readonly class CartService
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
        private TranslatorInterface $translator,
        private ContainerInterface $container,
        private RequestStack $requestStack,
        private CouponManager $couponManager,
        private Session $session
    ) {
    }

    public function addItem(Form $form, bool $validatedForm = false): void
    {
        $eventDispatcher = $this->eventDispatcher;
        $message = null;
        try {
            if ($validatedForm && !$form->isValid()) {
                throw new \RuntimeException('Failed to validate form');
            }
            $cartEvent = $this->getCartEvent();
            $cartEvent->bindForm($form);
            $eventDispatcher->dispatch($cartEvent, TheliaEvents::CART_ADDITEM);
            $this->afterModifyCart($eventDispatcher);
        } catch (PropelException $e) {
            Tlog::getInstance()->error(sprintf('Failed to add item to cart with message : %s', $e->getMessage()));
            $message = $this->translator->trans(
                'Failed to add this article to your cart, please try again'
            );
        } catch (FormValidationException $e) {
            $message = $e->getMessage();
        }
        if ($message) {
            throw new \RuntimeException($message);
        }
    }

    public function deleteItem(int $cartItemId): void
    {
        $eventDispatcher = $this->eventDispatcher;
        $cartEvent = $this->getCartEvent();
        $cartEvent->setCartItemId($cartItemId);
        try {
            $eventDispatcher->dispatch($cartEvent, TheliaEvents::CART_DELETEITEM);
            $this->afterModifyCart($eventDispatcher);
        } catch (\Exception $e) {
            Tlog::getInstance()->error(sprintf('error during deleting cartItem with message : %s', $e->getMessage()));
            throw new \RuntimeException('Failed to delete cartItem');
        }
    }

    public function changeItem(int $cartItemId, int $quantity): void
    {
        $eventDispatcher = $this->eventDispatcher;
        $cartEvent = $this->getCartEvent();
        $cartEvent->setCartItemId($cartItemId);
        $cartEvent->setQuantity($quantity);
        try {
            $eventDispatcher->dispatch($cartEvent, TheliaEvents::CART_UPDATEITEM);
            $this->afterModifyCart($eventDispatcher);
        } catch (\Exception $e) {
            Tlog::getInstance()->error(sprintf('Failed to change cart item quantity: %s', $e->getMessage()));
            throw new \RuntimeException('Failed to change cart item quantity');
        }
    }

    public function getCart(): ?Cart
    {
        return $this->requestStack->getCurrentRequest()?->getSession()->getSessionCart($this->eventDispatcher);
    }

    public function clearCart(): void
    {
        $this->requestStack->getCurrentRequest()?->getSession()->clearSessionCart($this->eventDispatcher);
    }

    /**
     * @throws PropelException
     */
    protected function afterModifyCart(EventDispatcherInterface $eventDispatcher): void
    {
        /** @var Session $session */
        $session = $this->requestStack->getCurrentRequest()?->getSession();
        if (null === $session) {
            return;
        }
        $order = $session->getOrder();
        if (null === $order) {
            return;
        }
        $deliveryModule = $order->getModuleRelatedByDeliveryModuleId();
        $deliveryAddress = AddressQuery::create()->findPk($order->getChoosenDeliveryAddress());
        if (null === $deliveryModule || null === $deliveryAddress) {
            return;
        }
        $moduleInstance = $deliveryModule->getDeliveryModuleInstance($this->container);
        $orderEvent = new OrderEvent($order);
        try {
            $deliveryPostageEvent = new DeliveryPostageEvent(
                $moduleInstance,
                $session->getSessionCart($eventDispatcher),
                $deliveryAddress
            );
            $eventDispatcher->dispatch(
                $deliveryPostageEvent,
                TheliaEvents::MODULE_DELIVERY_GET_POSTAGE,
            );
            $postage = $deliveryPostageEvent->getPostage();
            if (null !== $postage) {
                $orderEvent->setPostage($postage->getAmount());
                $orderEvent->setPostageTax($postage->getAmountTax());
                $orderEvent->setPostageTaxRuleTitle($postage->getTaxRuleTitle());
            }
            $eventDispatcher->dispatch($orderEvent, TheliaEvents::ORDER_SET_POSTAGE);
        } catch (\Exception) {
            // The postage has been chosen, but changes in the cart causes an exception.
            // Reset the postage data in the order
            $orderEvent->setDeliveryModule(0);
            $eventDispatcher->dispatch($orderEvent, TheliaEvents::ORDER_SET_DELIVERY_MODULE);
        }
    }

    protected function getCartEvent(): CartEvent
    {
        $session = $this->requestStack->getCurrentRequest()?->getSession();
        if (!$session instanceof Session) {
            throw new \RuntimeException('Failed to get cart event : no session found.');
        }
        $cart = $session->getSessionCart($this->eventDispatcher);
        if (null === $cart) {
            throw new \RuntimeException('Failed to get cart event : no cart in session.');
        }

        return new CartEvent($cart);
    }

    /**
     * Return the minimum expected postage for a cart in a given country.
     *
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function getEstimatedPostageForCountry(Cart $cart, Country $country, State $state = null): array
    {
        $orderSession = $this->session->getOrder();
        $deliveryModules = [];
        if ($deliveryModule = ModuleQuery::create()->findPk($orderSession->getDeliveryModuleId())) {
            $deliveryModules[] = $deliveryModule;
        }
        if (empty($deliveryModules)) {
            $deliveryModules = ModuleQuery::create()
                ->filterByActivate(1)
                ->filterByType(BaseModule::DELIVERY_MODULE_TYPE, Criteria::EQUAL)
                ->find();
        }
        $virtual = $cart->isVirtual();
        $postage = null;
        $postageTax = null;
        /** @var \Thelia\Model\Module $deliveryModule */
        foreach ($deliveryModules as $deliveryModule) {
            $areaDeliveryModule = AreaDeliveryModuleQuery::create()
                ->findByCountryAndModule($country, $deliveryModule, $state);
            if (null === $areaDeliveryModule && false === $virtual) {
                continue;
            }
            $moduleInstance = $deliveryModule->getDeliveryModuleInstance($this->container);
            if (true === $virtual && false === $moduleInstance->handleVirtualProductDelivery()) {
                continue;
            }
            try {
                $deliveryPostageEvent = new DeliveryPostageEvent($moduleInstance, $cart, null, $country, $state);
                $this->eventDispatcher->dispatch(
                    $deliveryPostageEvent,
                    TheliaEvents::MODULE_DELIVERY_GET_POSTAGE
                );
                if ($deliveryPostageEvent->isValidModule()) {
                    $modulePostage = $deliveryPostageEvent->getPostage();
                    if (null !== $modulePostage && (null === $postage || $postage > $modulePostage->getAmount())) {
                        $postage = $modulePostage->getAmount() - $modulePostage->getAmountTax();
                        $postageTax = $modulePostage->getAmountTax();
                    }
                }
            } catch (DeliveryException) {
                // Module is not available
            }
        }
        if ($this->isCouponRemovingPostage($country->getId(), $deliveryModule->getId())) {
            $postage = 0;
            $postageTax = 0;
        }

        return [
            'postage' => $postage,
            'tax' => $postageTax,
        ];
    }

    private function isCouponRemovingPostage(int $countryId, int $deliveryModuleId): bool
    {
        $couponsKept = $this->couponManager->getCouponsKept();
        if (\count($couponsKept) === 0) {
            return false;
        }
        /** @var CouponInterface $coupon */
        foreach ($couponsKept as $coupon) {
            if (!$coupon->isRemovingPostage()) {
                continue;
            }
            // Check if delivery country is on the list of countries for which delivery is free
            // If the list is empty, the shipping is free for all countries.
            $couponCountries = $coupon->getFreeShippingForCountries();
            if (!$couponCountries->isEmpty()) {
                $countryValid = false;
                /** @var CouponCountry $couponCountry */
                foreach ($couponCountries as $couponCountry) {
                    if ($countryId == $couponCountry->getCountryId()) {
                        $countryValid = true;
                        break;
                    }
                }
                if (!$countryValid) {
                    continue;
                }
            }
            // Check if shipping method is on the list of methods for which delivery is free
            // If the list is empty, the shipping is free for all methods.
            $couponModules = $coupon->getFreeShippingForModules();
            if (!$couponModules->isEmpty()) {
                $moduleValid = false;
                /** @var CouponModule $couponModule */
                foreach ($couponModules as $couponModule) {
                    if ($deliveryModuleId === $couponModule->getModuleId()) {
                        $moduleValid = true;
                        break;
                    }
                }
                if (!$moduleValid) {
                    continue;
                }
            }

            // All conditions are met, the shipping is free !
            return true;
        }

        return false;
    }
}
