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

namespace Thelia\Service\Model;

use Exception;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Exception\PropelException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Form\Form;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Thelia\Core\Event\Cart\CartCheckoutEvent;
use Thelia\Core\Event\Cart\CartEvent;
use Thelia\Core\Event\Delivery\DeliveryPostageEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Coupon\CouponManager;
use Thelia\Coupon\Type\CouponInterface;
use Thelia\Exception\Checkout\EmptyCartException;
use Thelia\Exception\Checkout\InvalidDeliveryException;
use Thelia\Exception\Checkout\InvalidPaymentException;
use Thelia\Exception\Checkout\MissingAddressException;
use Thelia\Form\Exception\FormValidationException;
use Thelia\Log\Tlog;
use Thelia\Model\AddressQuery;
use Thelia\Model\AreaDeliveryModuleQuery;
use Thelia\Model\Cart;
use Thelia\Model\Country;
use Thelia\Model\CouponCountry;
use Thelia\Model\CouponModule;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;
use Thelia\Model\OrderPostage;
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
        private Session $session,
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
            $this->afterModifyCart();
        } catch (PropelException $e) {
            Tlog::getInstance()->error(\sprintf('Failed to add item to cart with message : %s', $e->getMessage()));
            $message = $this->translator->trans(
                'Failed to add this article to your cart, please try again',
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
            $this->afterModifyCart();
        } catch (\Exception $e) {
            Tlog::getInstance()->error(\sprintf('error during deleting cartItem with message : %s', $e->getMessage()));
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
            $this->afterModifyCart();
        } catch (\Exception $e) {
            Tlog::getInstance()->error(\sprintf('Failed to change cart item quantity: %s', $e->getMessage()));
            throw new \RuntimeException('Failed to change cart item quantity');
        }
    }

    public function handlePostageOnCart(): void
    {
        try {
            $this->eventDispatcher->dispatch(new CartCheckoutEvent($this->getCart()), TheliaEvents::CART_SET_POSTAGE);
        } catch (\Exception $e) {
            $this->clearCartPostage();

            Tlog::getInstance()->error(\sprintf('Failed to set postage : %s', $e->getMessage()));
            throw new \RuntimeException('Failed to set postage');
        }
    }

    public function setDeliveryModule(?int $deliveryModuleId = null): void
    {
        try {
            $cartCheckoutEvent = new CartCheckoutEvent($this->getCart());
            $cartCheckoutEvent->setDeliveryModuleId($deliveryModuleId);
            $this->eventDispatcher->dispatch($cartCheckoutEvent, TheliaEvents::CART_SET_DELIVERY_MODULE);

            $this->handlePostageOnCart();
        } catch (\Exception $e) {
            Tlog::getInstance()->error(\sprintf('Failed to set delivery module : %s', $e->getMessage()));
            throw new \RuntimeException('Failed to set delivery module');
        }
    }

    public function setDeliveryAddress(?int $deliveryAddressId = null): void
    {
        try {
            $cartCheckoutEvent = new CartCheckoutEvent($this->getCart());
            $cartCheckoutEvent->setDeliveryAddressId($deliveryAddressId);
            $cartCheckoutEvent->setDeliveryModuleId(null);
            $this->eventDispatcher->dispatch($cartCheckoutEvent, TheliaEvents::CART_SET_DELIVERY_ADDRESS);

            $this->handlePostageOnCart();
        } catch (\Exception $e) {
            Tlog::getInstance()->error(\sprintf('Failed to set delivery address : %s', $e->getMessage()));
            throw new \RuntimeException('Failed to set delivery address');
        }
    }

    public function setInvoiceAddress(?int $invoiceAddressId = null): void
    {
        try {
            $cartCheckoutEvent = new CartCheckoutEvent($this->getCart());
            $cartCheckoutEvent->setInvoiceAddressId($invoiceAddressId);
            $this->eventDispatcher->dispatch($cartCheckoutEvent, TheliaEvents::CART_SET_INVOICE_ADDRESS);
        } catch (\Exception $e) {
            Tlog::getInstance()->error(\sprintf('Failed to set invoice address : %s', $e->getMessage()));
            throw new \RuntimeException('Failed to set invoice address');
        }
    }

    public function setPaymentModule(?int $paymentModuleId = null): void
    {
        try {
            $cartCheckoutEvent = new CartCheckoutEvent($this->getCart());
            $cartCheckoutEvent->setPaymentModuleId($paymentModuleId);
            $this->eventDispatcher->dispatch($cartCheckoutEvent, TheliaEvents::CART_SET_PAYMENT_MODULE);
        } catch (\Exception $e) {
            Tlog::getInstance()->error(\sprintf('Failed to set payment module : %s', $e->getMessage()));
            throw new \RuntimeException('Failed to set payment module');
        }
    }

    public function getCart(): Cart
    {
        $request = $this->requestStack->getCurrentRequest();

        if (!$request) {
            throw new \RuntimeException('Request not set !');
        }

        return $request->getSession()->getSessionCart($this->eventDispatcher);
    }

    public function clearCart(): void
    {
        $this->requestStack->getCurrentRequest()?->getSession()->clearSessionCart($this->eventDispatcher);
    }

    /**
     * @throws EmptyCartException|PropelException
     */
    public function checkCartNotEmpty(): void
    {
        $cart = $this->getCart();
        if (!$cart || $cart->countCartItems() == 0) {
            throw new EmptyCartException('Cart is empty or contains no items');
        }
    }

    /**
     * @throws MissingAddressException
     */
    public function checkDeliveryAddress(): void
    {
        $cart = $this->getCart();

        if (!$cart || !$cart->getAddressDeliveryId()) {
            throw new MissingAddressException('Delivery address is required');
        }

        $address = AddressQuery::create()->findPk($cart->getAddressDeliveryId());
        if (!$address) {
            throw new MissingAddressException('Delivery address not found');
        }
    }

    /**
     * @throws MissingAddressException
     */
    public function checkInvoiceAddress(): void
    {
        $cart = $this->getCart();

        if (!$cart || !$cart->getAddressInvoiceId()) {
            throw new MissingAddressException('Invoice address is required');
        }

        $address = AddressQuery::create()->findPk($cart->getAddressInvoiceId());
        if (!$address) {
            throw new MissingAddressException('Invoice address not found');
        }
    }

    /**
     * @throws InvalidDeliveryException
     */
    public function checkValidDelivery(): void
    {
        $cart = $this->getCart();

        $this->checkDeliveryAddress();

        if (!$cart->getDeliveryModuleId()) {
            throw new InvalidDeliveryException('Delivery module is required');
        }

        $module = ModuleQuery::create()->findPk($cart->getDeliveryModuleId());
        if (!$module) {
            throw new InvalidDeliveryException('Delivery module not found');
        }
    }

    /**
     * @throws InvalidPaymentException
     */
    public function checkValidPayment(): void
    {
        $cart = $this->getCart();

        if (!$cart || !$cart->getPaymentModuleId()) {
            throw new InvalidPaymentException('Payment module is required');
        }

        $module = ModuleQuery::create()->findPk($cart->getPaymentModuleId());
        if (!$module) {
            throw new InvalidPaymentException('Payment module not found');
        }
    }

    public function clearCartPostage(): void
    {
        $this->getCart()
            ->setPostage(null)
            ->setPostageTax(null)
            ->setPostageTaxRuleTitle(null)
            ->save();
    }

    /**
     * @throws InvalidDeliveryException
     */
    protected function afterModifyCart(): void
    {
        try {
            $this->handlePostageOnCart();
        } catch (\Exception) {
            // The postage has been chosen, but changes in the cart cause an exception.
            // Reset the postage data in the order
            $this->setDeliveryModule(null);
        }
    }

    protected function getCartEvent(): CartEvent
    {
        $session = $this->requestStack->getCurrentRequest()?->getSession();

        if (!$session instanceof Session) {
            throw new \RuntimeException('Failed to get cart event : no session found.');
        }

        $cart = $session->getSessionCart($this->eventDispatcher);

        if (!$cart instanceof Cart) {
            throw new \RuntimeException('Failed to get cart event : no cart in session.');
        }

        return new CartEvent($cart);
    }

    /**
     * Return the minimum expected postage for a cart in a given country.
     *
     * @throws PropelException
     */
    public function getEstimatedPostageForCountry(Cart $cart, Country $country, ?State $state = null): array
    {
        $orderSession = $this->session->getOrder();
        $deliveryModules = [];

        if ($deliveryModule = ModuleQuery::create()->findPk($orderSession->getDeliveryModuleId())) {
            $deliveryModules[] = $deliveryModule;
        }

        if ([] === $deliveryModules) {
            $deliveryModules = ModuleQuery::create()
                ->filterByActivate(1)
                ->filterByType(BaseModule::DELIVERY_MODULE_TYPE, Criteria::EQUAL)
                ->find();
        }

        $virtual = $cart->isVirtual();
        $postage = null;
        $postageTax = null;
        /** @var Module $deliveryModule */
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
                    TheliaEvents::MODULE_DELIVERY_GET_POSTAGE,
                );

                if ($deliveryPostageEvent->isValidModule()) {
                    $modulePostage = $deliveryPostageEvent->getPostage();

                    if ($modulePostage instanceof OrderPostage && (null === $postage || $postage > $modulePostage->getAmount())) {
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

    public function isCouponRemovingPostage(int $countryId, int $deliveryModuleId): bool
    {
        $couponsKept = $this->couponManager->getCouponsKept();

        if ([] === $couponsKept) {
            return false;
        }

        /** @var CouponInterface $coupon */
        foreach ($couponsKept as $coupon) {
            if (!$coupon->isRemovingPostage()) {
                continue;
            }

            // Check if the delivery country is on the list of countries for which delivery is free
            // If the list is empty, the shipping is free for all countries.
            $couponCountries = $coupon->getFreeShippingForCountries();

            if (!$couponCountries->isEmpty()) {
                $countryValid = false;

                /** @var CouponCountry $couponCountry */
                foreach ($couponCountries as $couponCountry) {
                    if ($countryId === $couponCountry->getCountryId()) {
                        $countryValid = true;
                        break;
                    }
                }

                if (!$countryValid) {
                    continue;
                }
            }

            // Check if the shipping method is on the list of methods for which delivery is free
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

            // All conditions are met, the shipping is free!
            return true;
        }

        return false;
    }
}
