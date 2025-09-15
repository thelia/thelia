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

namespace Thelia\Action;

use Propel\Runtime\Exception\PropelException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Core\Event\Cart\CartCheckoutEvent;
use Thelia\Core\Event\Cart\CartCreateEvent;
use Thelia\Core\Event\Cart\CartDuplicationEvent;
use Thelia\Core\Event\Cart\CartEvent;
use Thelia\Core\Event\Cart\CartPersistEvent;
use Thelia\Core\Event\Cart\CartRestoreEvent;
use Thelia\Core\Event\Currency\CurrencyChangeEvent;
use Thelia\Core\Event\Delivery\DeliveryPostageEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Core\Security\SecurityContext;
use Thelia\Domain\Cart\Exception\NotEnoughStockException;
use Thelia\Model\AddressQuery;
use Thelia\Model\Base\CustomerQuery;
use Thelia\Model\Base\ProductSaleElementsQuery;
use Thelia\Model\Cart as CartModel;
use Thelia\Model\CartItem;
use Thelia\Model\CartItemQuery;
use Thelia\Model\CartQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Currency as CurrencyModel;
use Thelia\Model\Customer as CustomerModel;
use Thelia\Model\ModuleQuery;
use Thelia\Model\OrderPostage;
use Thelia\Model\ProductSaleElements;
use Thelia\Model\Tools\ProductPriceTools;
use Thelia\Tools\TokenProvider;

/**
 * Class Cart where all actions are manage like adding, modifying or delete items.
 *
 * Class Cart
 *
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class Cart extends BaseAction implements EventSubscriberInterface
{
    public function __construct(
        protected RequestStack $requestStack,
        protected TokenProvider $tokenProvider,
        protected SecurityContext $securityContext,
        protected ContainerInterface $container,
    ) {
    }

    /**
     * @throws PropelException
     */
    public function setDeliveryAddress(CartCheckoutEvent $event): void
    {
        $cart = $event->getCart();
        $addressId = $event->getDeliveryAddressId();

        if ($addressId && !$this->validateAddressOwnership($cart, $event->getDeliveryAddressId())) {
            return;
        }

        $cart->setAddressDeliveryId($addressId)->save();
    }

    public function setPaymentModule(CartCheckoutEvent $event): void
    {
        $cart = $event->getCart();
        $cart->setPaymentModuleId($event->getPaymentModuleId());
        $cart->save();
    }

    public function setDeliveryModule(CartCheckoutEvent $event): void
    {
        $cart = $event->getCart();
        $cart->setDeliveryModuleId($event->getDeliveryModuleId());
        $cart->save();
    }

    public function calculatePostage(CartCheckoutEvent $event, $eventName, EventDispatcherInterface $dispatcher): void
    {
        $cart = $event->getCart();
        $moduleId = $event->getDeliveryModuleId();
        $deliveryAddressId = $event->getDeliveryAddressId();

        if (null === $moduleId || null === $deliveryAddressId) {
            return;
        }

        try {
            $postage = $this->getPostageByDeliveryModuleId($cart, $dispatcher, $moduleId, $deliveryAddressId);
            $cart
                ->setPostage($postage->getAmount() - $postage->getAmountTax())
                ->setPostageTax($postage->getAmountTax())
                ->setPostageTaxRuleTitle($postage->getTaxRuleTitle())
                ->save();
        } catch (\Exception $e) {
            // If an exception is thrown here, we just ignore it.
            // The delivery module will not be set on the cart.
        }
    }

    public function setInvoiceAddress(CartCheckoutEvent $event): void
    {
        $cart = $event->getCart();
        $addressId = $event->getInvoiceAddressId();

        if ($addressId && !$this->validateAddressOwnership($cart, $event->getInvoiceAddressId())) {
            return;
        }

        $cart->setAddressInvoiceId($addressId)->save();
    }

    public function persistCart(CartPersistEvent $event): void
    {
        $cart = $event->getCart();

        if ($cart->isNew()) {
            $cart
                ->setToken($this->generateCartCookieIdentifier())
                ->save();
            $this->getSession()->setSessionCart($cart);
        }
    }

    /**
     * add an article in the current cart.
     *
     * @throws PropelException
     */
    public function addItem(CartEvent $event, $eventName, EventDispatcherInterface $dispatcher): void
    {
        $cart = $event->getCart();
        $append = $event->getAppend();
        $quantity = $event->getQuantity();
        $currency = $cart->getCurrency();
        $customer = $cart->getCustomer();
        $discount = 0;

        if ($cart->isNew()) {
            $persistEvent = new CartPersistEvent($cart);
            $dispatcher->dispatch($persistEvent, TheliaEvents::CART_PERSIST);
        }

        if (null !== $customer && $customer->getDiscount() > 0) {
            $discount = $customer->getDiscount();
        }

        $productSaleElementsId = $event->getProductSaleElementsId();
        $productId = $event->getProductId();

        // Search for an identical item in the cart
        $findItemEvent = clone $event;
        $dispatcher->dispatch($findItemEvent, TheliaEvents::CART_FINDITEM);

        $cartItem = $findItemEvent->getCartItem();

        if ($cartItem instanceof CartItem) {
            if ($append) {
                $cartItem->addQuantity($quantity)->save();
            } else {
                $cartItem->setQuantity($quantity)->save();
            }
        } else {
            $productSaleElements = ProductSaleElementsQuery::create()->findPk($productSaleElementsId);

            if (null !== $productSaleElements) {
                $productPrices = $productSaleElements->getPricesByCurrency(
                    $currency ?? CurrencyModel::getDefaultCurrency(),
                    $discount,
                );

                $cartItem = $this->doAddItem($dispatcher, $cart, $productId, $productSaleElements, $quantity, $productPrices);
            }
        }

        $event->setCartItem($cartItem);
    }

    /**
     * Delete specify article present into cart.
     */
    public function deleteItem(CartEvent $event): void
    {
        if (null !== $cartItemId = $event->getCartItemId()) {
            $cart = $event->getCart();
            CartItemQuery::create()
                ->filterByCartId($cart->getId())
                ->filterById($cartItemId)
                ->delete();

            // Force an update of the Cart object to provide
            // to other listeners an updated CartItem collection.
            $cart->clearCartItems();
        }
    }

    /**
     * Clear the cart.
     */
    public function clear(CartEvent $event): void
    {
        $cart = $event->getCart();
        $cart->delete();
    }

    /**
     * Modify article's quantity.
     *
     * don't use Form here just test the Request.
     *
     * @throws PropelException
     * @throws NotEnoughStockException
     */
    public function changeItem(CartEvent $event, $eventName, EventDispatcherInterface $dispatcher): void
    {
        if ((null !== $cartItemId = $event->getCartItemId()) && (null !== $quantity = $event->getQuantity())) {
            $cart = $event->getCart();

            $cartItem = CartItemQuery::create()
                ->filterByCartId($cart->getId())
                ->filterById($cartItemId)
                ->findOne();

            if ($cartItem) {
                $event->setCartItem(
                    $this->updateQuantity($dispatcher, $cartItem, $quantity),
                );
            }
        }
    }

    public function updateCart(CurrencyChangeEvent $event, $eventName, EventDispatcherInterface $dispatcher): void
    {
        $cart = $event->getRequest()->getSession()->getSessionCart($dispatcher);

        if ($cart instanceof CartModel) {
            $this->updateCartPrices($cart, $event->getCurrency());
        }
    }

    /**
     * Refresh article's price.
     */
    public function updateCartPrices(CartModel $cart, CurrencyModel $currency): void
    {
        $customer = $cart->getCustomer();
        $discount = 0;

        if (null !== $customer && $customer->getDiscount() > 0) {
            $discount = $customer->getDiscount();
        }

        // cart item
        foreach ($cart->getCartItems() as $cartItem) {
            $productSaleElements = $cartItem->getProductSaleElements();

            $productPrice = $productSaleElements->getPricesByCurrency($currency, $discount);

            $cartItem
                ->setPrice($productPrice->getPrice())
                ->setPromoPrice($productPrice->getPromoPrice());

            $cartItem->save();
        }

        // update the currency cart
        $cart->setCurrencyId($currency->getId());
        $cart->save();
    }

    /**
     * increase the quantity for an existing cartItem.
     *
     * @throws \Exception
     * @throws PropelException
     * @throws NotEnoughStockException
     */
    protected function updateQuantity(EventDispatcherInterface $dispatcher, CartItem $cartItem, float $quantity): CartItem
    {
        $cartItem->setDispatcher($dispatcher);
        $cartItem->updateQuantity($quantity)
            ->save();

        return $cartItem;
    }

    /**
     * try to attach a new item to an existing cart.
     */
    protected function doAddItem(
        EventDispatcherInterface $dispatcher,
        CartModel $cart,
        int $productId,
        ProductSaleElements $productSaleElements,
        float $quantity,
        ProductPriceTools $productPrices,
    ): CartItem {
        $cartItem = new CartItem();
        $cartItem->setDispatcher($dispatcher);
        $cartItem
            ->setCart($cart)
            ->setProductId($productId)
            ->setProductSaleElementsId($productSaleElements->getId())
            ->setQuantity($quantity)
            ->setPrice($productPrices->getPrice())
            ->setPromoPrice($productPrices->getPromoPrice())
            ->setPromo($productSaleElements->getPromo())
            ->setPriceEndOfLife(time() + ConfigQuery::read('cart.priceEOF', 60 * 60 * 24 * 30))
            ->save();

        return $cartItem;
    }

    /**
     * find a specific record in CartItem table using the Cart id, the product id
     * and the product_sale_elements id.
     *
     * @deprecated this method is deprecated. Dispatch a TheliaEvents::CART_FINDITEM instead
     */
    protected function findItem(int $cartId, int $productId, int $productSaleElementsId): CartItem
    {
        return CartItemQuery::create()
            ->filterByCartId($cartId)
            ->filterByProductId($productId)
            ->filterByProductSaleElementsId($productSaleElementsId)
            ->findOne();
    }

    /**
     * Find a specific record in CartItem table using the current CartEvent.
     *
     * @param CartEvent $event the cart event
     */
    public function findCartItem(CartEvent $event): void
    {
        // Do not try to find a cartItem if one exists in the event, as previous event handlers
        // mays have put it in th event.
        if (
            !$event->getCartItem() instanceof CartItem && null !== $foundItem = CartItemQuery::create()
            ->filterByCartId($event->getCart()->getId())
            ->filterByProductId($event->getProductId())
            ->filterByProductSaleElementsId($event->getProductSaleElementsId())
            ->findOne()
        ) {
            $event->setCartItem($foundItem);
        }
    }

    /**
     * Search if cart already exists in session. If not try to restore it from the cart cookie,
     * or duplicate an old one.
     */
    public function restoreCurrentCart(CartRestoreEvent $cartRestoreEvent, $eventName, EventDispatcherInterface $dispatcher): void
    {
        $cookieName = ConfigQuery::read('cart.cookie_name', 'thelia_cart');
        $persistentCookie = ConfigQuery::read('cart.use_persistent_cookie', 1);
        $request = $this->requestStack->getMainRequest();

        $cart = null;

        if ($request->cookies->has($cookieName) && $persistentCookie) {
            $cart = $this->managePersistentCart($cartRestoreEvent, $cookieName, $dispatcher);
        } elseif (!$persistentCookie) {
            $cart = $this->manageNonPersistentCookie($cartRestoreEvent, $dispatcher);
        }

        // Still no cart ? Create a new one.
        if (!$cart instanceof CartModel) {
            $cart = $this->dispatchNewCart($dispatcher);
        }

        if ($cart->getCurrency()) {
            $this->getSession()->setCurrency($cart->getCurrency());
        }

        $cartRestoreEvent->setCart($cart);
    }

    /**
     * The cart token is not saved in a cookie, if the cart is present in session, we just change the customer id
     * if needed or create duplicate the current cart if the customer is not the same as customer already present in
     * the cart.
     *
     * @throws \Exception
     * @throws PropelException
     */
    protected function manageNonPersistentCookie(CartRestoreEvent $cartRestoreEvent, EventDispatcherInterface $dispatcher): CartModel
    {
        $cart = $cartRestoreEvent->getCart();

        if (!$cart instanceof CartModel) {
            $cart = $this->dispatchNewCart($dispatcher);
        } else {
            $cart = $this->manageCartDuplicationAtCustomerLogin($cart, $dispatcher);
        }

        return $cart;
    }

    protected function validateAddressOwnership(CartModel $cart, int $addressId): bool
    {
        if (!$customerId = $cart->getCustomerId()) {
            return false;
        }

        $address = AddressQuery::create()
            ->filterByCustomerId($customerId)
            ->filterById($addressId)
            ->findOne();

        if (!$address) {
            return false;
        }

        return true;
    }

    /**
     * The cart token is saved in a cookie so we try to retrieve it. Then the customer is checked.
     *
     * @throws \Exception
     * @throws PropelException
     */
    protected function managePersistentCart(CartRestoreEvent $cartRestoreEvent, string $cookieName, EventDispatcherInterface $dispatcher): CartModel
    {
        $request = $this->requestStack->getMainRequest();

        // The cart cookie exists -> get the cart token
        $token = $request->cookies->get($cookieName);

        // Check if a cart exists for this token
        if (null !== $cart = CartQuery::create()->findOneByToken($token)) {
            $cart = $this->manageCartDuplicationAtCustomerLogin($cart, $dispatcher);
        }

        return $cart;
    }

    protected function manageCartDuplicationAtCustomerLogin(CartModel $cart, EventDispatcherInterface $dispatcher)
    {
        /** @var CustomerModel $customer */
        if (null !== $customer = $this->getSession()->getCustomerUser()) {
            // Check if we have to duplicate the existing cart.

            $duplicateCart = true;

            // A customer is logged in.
            // If the customer has a discount, whe have to duplicate the cart,
            // so that the discount will be applied to the products in cart.
            if (null === $cart->getCustomerId() && (0 === $customer->getDiscount() || 0 === $cart->countCartItems())) {
                // If no discount, or an empty cart, there's no need to duplicate.
                $duplicateCart = false;
            }

            if ($duplicateCart) {
                // Duplicate the cart
                $cart = $this->duplicateCart($dispatcher, $cart, $customer);
            } else {
                // No duplication required, just assign the cart to the customer
                $cart->setCustomerId($customer->getId())->save();
            }
        } elseif (null !== $cart->getCustomerId()) {
            // The cart belongs to another user
            if (0 === $cart->countCartItems()) {
                // No items in cart, assign it to nobody.
                $cart->setCustomerId(null)->save();
            } else {
                // Some itemls in cart, duplicate it without assigning a customer ID.
                $cart = $this->duplicateCart($dispatcher, $cart);
            }
        }

        return $cart;
    }

    /**
     * @throws PropelException
     */
    protected function getPostageByDeliveryModuleId(
        CartModel $cart,
        EventDispatcherInterface $dispatcher,
        int $moduleId,
        int $deliveryAddressId,
    ): OrderPostage {
        if (!$customer = $this->securityContext->getCustomerUser()) {
            throw new \Exception('Customer not found !');
        }

        $deliveryModule = ModuleQuery::create()->filterById($moduleId)->findOne();
        $moduleInstance = $deliveryModule->getDeliveryModuleInstance($this->container);

        $deliveryAddress = AddressQuery::create()
            ->useCustomerQuery()
            ->filterById($customer->getId())
            ->endUse()
            ->filterById($deliveryAddressId)->findOne();

        if (!$deliveryAddress) {
            throw new \Exception('Delivery address not found !');
        }

        if (true === $cart->isVirtual() && false === $moduleInstance->handleVirtualProductDelivery()) {
            throw new \Exception('Virtual product delivery failed ! ');
        }

        $country = $deliveryAddress->getCountry();
        $state = $deliveryAddress->getState();

        $deliveryPostageEvent = new DeliveryPostageEvent($moduleInstance, $cart, $deliveryAddress, $country, $state);

        $dispatcher->dispatch(
            $deliveryPostageEvent,
            TheliaEvents::MODULE_DELIVERY_GET_POSTAGE
        );

        if ($deliveryPostageEvent->getPostage()) {
            return $deliveryPostageEvent->getPostage();
        }

        return new OrderPostage();
    }

    protected function dispatchNewCart(EventDispatcherInterface $dispatcher): CartModel
    {
        $cartCreateEvent = new CartCreateEvent();

        $dispatcher->dispatch($cartCreateEvent, TheliaEvents::CART_CREATE_NEW);

        return $cartCreateEvent->getCart();
    }

    /**
     * Create a new, empty cart object, and assign it to the current customer, if any.
     */
    public function createEmptyCart(CartCreateEvent $cartCreateEvent): void
    {
        $cart = new CartModel();

        $cart->setCurrency($this->getSession()->getCurrency(true));

        /** @var CustomerModel $customer */
        if (null !== $customer = $this->getSession()->getCustomerUser()) {
            $cart->setCustomer(CustomerQuery::create()->findPk($customer->getId()));
        }

        $this->getSession()->setSessionCart($cart);

        if (1 === ConfigQuery::read('cart.use_persistent_cookie', 1)) {
            // set cart_use_cookie to "" to remove the cart cookie
            // see Thelia\Core\EventListener\ResponseListener
            $this->getSession()->set('cart_use_cookie', '');
        }

        $cartCreateEvent->setCart($cart);
    }

    /**
     * Duplicate an existing Cart. If a customer ID is provided the created cart will be attached to this customer.
     */
    protected function duplicateCart(EventDispatcherInterface $dispatcher, CartModel $cart, ?CustomerModel $customer = null): CartModel
    {
        $newCart = $cart->duplicate(
            $this->generateCartCookieIdentifier(),
            $customer,
            $this->getSession()->getCurrency(),
            $dispatcher,
        );

        $cartEvent = new CartDuplicationEvent($newCart, $cart);
        $dispatcher->dispatch($cartEvent, TheliaEvents::CART_DUPLICATE);

        return $cartEvent->getDuplicatedCart();
    }

    /**
     * Generate the cart cookie identifier, or return null if the cart is only managed in the session object,
     * not in a client cookie.
     */
    protected function generateCartCookieIdentifier(): ?string
    {
        $id = null;

        if (1 === ConfigQuery::read('cart.use_persistent_cookie', 1)) {
            $id = $this->tokenProvider->getToken();
            $this->getSession()->set('cart_use_cookie', $id);
        }

        return $id;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            TheliaEvents::CART_SET_DELIVERY_ADDRESS => ['setDeliveryAddress', 128],
            TheliaEvents::CART_SET_DELIVERY_MODULE => ['setDeliveryModule', 128],
            TheliaEvents::CART_SET_POSTAGE => ['calculatePostage', 128],
            TheliaEvents::CART_SET_INVOICE_ADDRESS => ['setInvoiceAddress', 128],
            TheliaEvents::CART_SET_PAYMENT_MODULE => ['setPaymentModule', 128],
            TheliaEvents::CART_PERSIST => ['persistCart', 128],
            TheliaEvents::CART_RESTORE_CURRENT => ['restoreCurrentCart', 128],
            TheliaEvents::CART_CREATE_NEW => ['createEmptyCart', 128],
            TheliaEvents::CART_ADDITEM => ['addItem', 128],
            TheliaEvents::CART_FINDITEM => ['findCartItem', 128],
            TheliaEvents::CART_DELETEITEM => ['deleteItem', 128],
            TheliaEvents::CART_UPDATEITEM => ['changeItem', 128],
            TheliaEvents::CART_CLEAR => ['clear', 128],
            TheliaEvents::CHANGE_DEFAULT_CURRENCY => ['updateCart', 128],
        ];
    }

    protected function getSession(): Session
    {
        /** @var Session $session */
        $session = $this->requestStack->getMainRequest()?->getSession();

        if (null === $session) {
            throw new \RuntimeException('No session available');
        }

        return $session;
    }
}
