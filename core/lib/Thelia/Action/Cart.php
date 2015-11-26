<?php
/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace Thelia\Action;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Core\Event\Cart\CartCreateEvent;
use Thelia\Core\Event\Cart\CartPersistEvent;
use Thelia\Core\Event\Cart\CartRestoreEvent;
use Thelia\Core\Event\Cart\CartEvent;
use Thelia\Core\Event\Currency\CurrencyChangeEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Model\Base\CustomerQuery;
use Thelia\Model\Base\ProductSaleElementsQuery;
use Thelia\Model\Currency as CurrencyModel;
use Thelia\Model\CartItem;
use Thelia\Model\Cart as CartModel;
use Thelia\Model\CartItemQuery;
use Thelia\Model\CartQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Customer as CustomerModel;
use Thelia\Model\ProductSaleElements;
use Thelia\Model\Tools\ProductPriceTools;
use Thelia\Tools\TokenProvider;

/**
 *
 * Class Cart where all actions are manage like adding, modifying or delete items.
 *
 * Class Cart
 * @package Thelia\Action
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class Cart extends BaseAction implements EventSubscriberInterface
{
    /** @var Request  */
    protected $request;

    /** @var Session  */
    protected $session;

    /** @var  TokenProvider */
    protected $tokenProvider;

    public function __construct(Request $request, TokenProvider $tokenProvider)
    {
        $this->request = $request;

        $this->session = $request->getSession();

        $this->tokenProvider = $tokenProvider;
    }

    public function persistCart(CartPersistEvent $event)
    {
        $cart = $event->getCart();

        if ($cart->isNew()) {
            $cart
                ->setToken($this->generateCartCookieIdentifier())
                ->save();
            $this->session->setSessionCart($cart);
        }
    }

    /**
     *
     * add an article in the current cart
     * @param \Thelia\Core\Event\Cart\CartEvent $event
     */
    public function addItem(CartEvent $event)
    {
        $cart = $event->getCart();
        $newness = $event->getNewness();
        $append = $event->getAppend();
        $quantity = $event->getQuantity();
        $currency = $cart->getCurrency();
        $customer = $cart->getCustomer();
        $discount = 0;

        if ($cart->isNew()) {
            $persistEvent = new CartPersistEvent($cart);
            $event->getDispatcher()->dispatch(TheliaEvents::CART_PERSIST, $persistEvent);
        }

        if (null !== $customer && $customer->getDiscount() > 0) {
            $discount = $customer->getDiscount();
        }

        $productSaleElementsId = $event->getProductSaleElementsId();
        $productId = $event->getProduct();

        // Search for an identical item in the cart
        $event->getDispatcher()->dispatch(TheliaEvents::CART_FINDITEM, $event);

        $cartItem = $event->getCartItem();

        if ($cartItem === null || $newness) {
            $productSaleElements = ProductSaleElementsQuery::create()->findPk($productSaleElementsId);

            if (null !== $productSaleElements) {
                $productPrices = $productSaleElements->getPricesByCurrency($currency, $discount);

                $cartItem = $this->doAddItem($event->getDispatcher(), $cart, $productId, $productSaleElements, $quantity, $productPrices);
            }
        } elseif ($append && $cartItem !== null) {
            $cartItem->addQuantity($quantity)->save();
        }

        $event->setCartItem($cartItem);
    }

    /**
     *
     * Delete specify article present into cart
     *
     * @param \Thelia\Core\Event\Cart\CartEvent $event
     */
    public function deleteItem(CartEvent $event)
    {
        if (null !== $cartItemId = $event->getCartItemId()) {
            $cart = $event->getCart();
            CartItemQuery::create()
                ->filterByCartId($cart->getId())
                ->filterById($cartItemId)
                ->delete();
        }
    }

    /**
     * Clear the cart
     * @param CartEvent $event
     */
    public function clear(CartEvent $event)
    {
        if (null !== $cart = $event->getCart()) {
            $cart->delete();
        }
    }

    /**
     *
     * Modify article's quantity
     *
     * don't use Form here just test the Request.
     *
     * @param \Thelia\Core\Event\Cart\CartEvent $event
     */
    public function changeItem(CartEvent $event)
    {
        if ((null !== $cartItemId = $event->getCartItemId()) && (null !== $quantity = $event->getQuantity())) {
            $cart = $event->getCart();

            $cartItem = CartItemQuery::create()
                ->filterByCartId($cart->getId())
                ->filterById($cartItemId)
                ->findOne();

            if ($cartItem) {
                $event->setCartItem(
                    $this->updateQuantity($event->getDispatcher(), $cartItem, $quantity)
                );
            }
        }
    }

    public function updateCart(CurrencyChangeEvent $event)
    {
        $cart = $this->session->getSessionCart($event->getDispatcher());

        if (null !== $cart) {
            $this->updateCartPrices($cart, $event->getCurrency());
        }
    }

    /**
     *
     * Refresh article's price
     *
     * @param \Thelia\Model\Cart     $cart
     * @param \Thelia\Model\Currency $currency
     */
    public function updateCartPrices(CartModel $cart, CurrencyModel $currency)
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
     * increase the quantity for an existing cartItem
     *
     * @param EventDispatcherInterface $dispatcher
     * @param CartItem $cartItem
     * @param float $quantity
     *
     * @throws \Exception
     * @throws \Propel\Runtime\Exception\PropelException
     * @return CartItem
     */
    protected function updateQuantity(EventDispatcherInterface $dispatcher, CartItem $cartItem, $quantity)
    {
        $cartItem->setDisptacher($dispatcher);
        $cartItem->updateQuantity($quantity)
            ->save();

        return $cartItem;
    }

    /**
     * try to attach a new item to an existing cart
     *
     * @param EventDispatcherInterface $dispatcher
     * @param \Thelia\Model\Cart       $cart
     * @param int                      $productId
     * @param ProductSaleElements      $productSaleElements
     * @param float                    $quantity
     * @param ProductPriceTools        $productPrices
     *
     * @return CartItem
     */
    protected function doAddItem(
        EventDispatcherInterface $dispatcher,
        CartModel $cart,
        $productId,
        ProductSaleElements $productSaleElements,
        $quantity,
        ProductPriceTools $productPrices
    ) {
        $cartItem = new CartItem();
        $cartItem->setDisptacher($dispatcher);
        $cartItem
            ->setCart($cart)
            ->setProductId($productId)
            ->setProductSaleElementsId($productSaleElements->getId())
            ->setQuantity($quantity)
            ->setPrice($productPrices->getPrice())
            ->setPromoPrice($productPrices->getPromoPrice())
            ->setPromo($productSaleElements->getPromo())
            ->setPriceEndOfLife(time() + ConfigQuery::read("cart.priceEOF", 60*60*24*30))
            ->save();

        return $cartItem;
    }

    /**
     * find a specific record in CartItem table using the Cart id, the product id
     * and the product_sale_elements id
     *
     * @param  int           $cartId
     * @param  int           $productId
     * @param  int           $productSaleElementsId
     * @return CartItem
     *
     * @deprecated this method is deprecated. Dispatch a TheliaEvents::CART_FINDITEM instead
     */
    protected function findItem($cartId, $productId, $productSaleElementsId)
    {
        return CartItemQuery::create()
            ->filterByCartId($cartId)
            ->filterByProductId($productId)
            ->filterByProductSaleElementsId($productSaleElementsId)
            ->findOne();
    }

    /**
     * Find a specific record in CartItem table using the current CartEvent
     *
     * @param CartEvent $event the cart event
     */
    public function findCartItem(CartEvent $event)
    {
        if (null !== $foundItem = CartItemQuery::create()
            ->filterByCartId($event->getCart()->getId())
            ->filterByProductId($event->getProduct())
            ->filterByProductSaleElementsId($event->getProductSaleElementsId())
            ->findOne()) {
            $event->setCartItem($foundItem);
        }
    }

    /**
     * Search if cart already exists in session. If not try to restore it from the cart cookie,
     * or duplicate an old one.
     *
     * @param CartRestoreEvent $cartRestoreEvent
     */
    public function restoreCurrentCart(CartRestoreEvent $cartRestoreEvent)
    {
        $cookieName = ConfigQuery::read("cart.cookie_name", 'thelia_cart');
        $persistentCookie = ConfigQuery::read("cart.use_persistent_cookie", 1);

        $cart = null;

        if ($this->request->cookies->has($cookieName) && $persistentCookie) {
            $cart = $this->managePersistentCart($cartRestoreEvent, $cookieName);
        } elseif (!$persistentCookie) {
            $cart = $this->manageNonPersistentCookie($cartRestoreEvent);
        }

        // Still no cart ? Create a new one.
        if (null === $cart) {
            $cart = $this->dispatchNewCart($cartRestoreEvent->getDispatcher());
        }

        $cartRestoreEvent->setCart($cart);
    }

    /**
     * The cart token is not saved in a cookie, if the cart is present in session, we just change the customer id
     * if needed or create duplicate the current cart if the customer is not the same as customer already present in
     * the cart.
     *
     * @param CartRestoreEvent $cartRestoreEvent
     * @return CartModel
     * @throws \Exception
     * @throws \Propel\Runtime\Exception\PropelException
     */
    private function manageNonPersistentCookie(CartRestoreEvent $cartRestoreEvent)
    {
        $cart = $cartRestoreEvent->getCart();

        if (null === $cart) {
            $cart = $cart = $this->dispatchNewCart($cartRestoreEvent->getDispatcher());
        } else {
            $cart = $this->manageCartDuplicationAtCustomerLogin($cart, $cartRestoreEvent->getDispatcher());
        }

        return $cart;
    }

    /**
     *
     * The cart token is saved in a cookie so we try to retrieve it. Then the customer is checked.
     *
     * @param CartRestoreEvent $cartRestoreEvent
     * @param $cookieName
     * @return CartModel
     * @throws \Exception
     * @throws \Propel\Runtime\Exception\PropelException
     */
    private function managePersistentCart(CartRestoreEvent $cartRestoreEvent, $cookieName)
    {
        // The cart cookie exists -> get the cart token
        $token = $this->request->cookies->get($cookieName);

        // Check if a cart exists for this token
        if (null !== $cart = CartQuery::create()->findOneByToken($token)) {
            $cart = $this->manageCartDuplicationAtCustomerLogin($cart, $cartRestoreEvent->getDispatcher());
        }

        return $cart;
    }

    private function manageCartDuplicationAtCustomerLogin(CartModel $cart, EventDispatcherInterface $dispatcher)
    {
        /** @var CustomerModel $customer */
        if (null !== $customer = $this->session->getCustomerUser()) {
            // Check if we have to duplicate the existing cart.

            $duplicateCart = true;

            // A customer is logged in.
            if (null === $cart->getCustomerId()) {
                // If the customer has a discount, whe have to duplicate the cart,
                // so that the discount will be applied to the products in cart.

                if (0 === $customer->getDiscount() || 0 === $cart->countCartItems()) {
                    // If no discount, or an empty cart, there's no need to duplicate.
                    $duplicateCart = false;
                }
            }

            if ($duplicateCart) {
                // Duplicate the cart
                $cart = $this->duplicateCart($dispatcher, $cart, $customer);
            } else {
                // No duplication required, just assign the cart to the customer
                $cart->setCustomerId($customer->getId())->save();
            }
        } elseif ($cart->getCustomerId() != null) {
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
     * @param EventDispatcherInterface $dispatcher
     * @return CartModel
     */
    protected function dispatchNewCart(EventDispatcherInterface $dispatcher)
    {
        $cartCreateEvent = new CartCreateEvent();

        $dispatcher->dispatch(TheliaEvents::CART_CREATE_NEW, $cartCreateEvent);

        return $cartCreateEvent->getCart();
    }

    /**
     * Create a new, empty cart object, and assign it to the current customer, if any.
     *
     * @param CartCreateEvent $cartCreateEvent
     */
    public function createEmptyCart(CartCreateEvent $cartCreateEvent)
    {
        $cart = new CartModel();

        $cart->setCurrency($this->session->getCurrency(true));

        if (null !== $customer = $this->session->getCustomerUser()) {
            $cart->setCustomer(CustomerQuery::create()->findPk($customer->getId()));
        }

        $cartCreateEvent->setCart($cart);
    }

    /**
     * Duplicate an existing Cart. If a customer ID is provided the created cart will be attached to this customer.
     *
     * @param EventDispatcherInterface $dispatcher
     * @param CartModel $cart
     * @param CustomerModel $customer
     * @return CartModel
     */
    protected function duplicateCart(EventDispatcherInterface $dispatcher, CartModel $cart, CustomerModel $customer = null)
    {
        $newCart = $cart->duplicate(
            $this->generateCartCookieIdentifier(),
            $customer,
            $this->session->getCurrency(),
            $dispatcher
        );

        $cartEvent = new CartEvent($newCart);

        $dispatcher->dispatch(TheliaEvents::CART_DUPLICATE, $cartEvent);

        return $cartEvent->getCart();
    }

    /**
     * Generate the cart cookie identifier, or return null if the cart is only managed in the session object,
     * not in a client cookie.
     *
     * @return string
     */
    protected function generateCartCookieIdentifier()
    {
        $id = null;

        if (ConfigQuery::read("cart.use_persistent_cookie", 1) == 1) {
            $id = $this->tokenProvider->getToken();
            $this->session->set('cart_use_cookie', $id);
        }

        return $id;
    }


    /**
     * @inheritdoc
     */
    public static function getSubscribedEvents()
    {
        return array(
            TheliaEvents::CART_PERSIST => array("persistCart", 128),
            TheliaEvents::CART_RESTORE_CURRENT => array("restoreCurrentCart", 128),
            TheliaEvents::CART_CREATE_NEW => array("createEmptyCart", 128),
            TheliaEvents::CART_ADDITEM => array("addItem", 128),
            TheliaEvents::CART_FINDITEM => array("findCartItem", 128),
            TheliaEvents::CART_DELETEITEM => array("deleteItem", 128),
            TheliaEvents::CART_UPDATEITEM => array("changeItem", 128),
            TheliaEvents::CART_CLEAR => array("clear", 128),
            TheliaEvents::CHANGE_DEFAULT_CURRENCY => array("updateCart", 128),
        );
    }
}
