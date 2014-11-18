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
use Thelia\Core\Event\Cart\CartRestoreEvent;
use Thelia\Core\Event\Cart\CartEvent;
use Thelia\Core\Event\Currency\CurrencyChangeEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Log\Tlog;
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
 * @author Manuel Raynaud <manu@thelia.net>
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

        if (null !== $customer && $customer->getDiscount() > 0) {
            $discount = $customer->getDiscount();
        }

        $productSaleElementsId = $event->getProductSaleElementsId();
        $productId = $event->getProduct();

        $cartItem = $this->findItem($cart->getId(), $productId, $productSaleElementsId);

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
    protected function doAddItem(EventDispatcherInterface $dispatcher, CartModel $cart, $productId, ProductSaleElements $productSaleElements, $quantity, ProductPriceTools $productPrices)
    {
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
     * Search if cart already exists in session. If not try to restore it from the cart cookie,
     * or duplicate an old one.
     *
     * @param CartRestoreEvent $cartRestoreEvent
     */
    public function restoreCurrentCart(CartRestoreEvent $cartRestoreEvent)
    {
        $cookieName = ConfigQuery::read("cart.cookie_name", 'thelia_cart');

        $cart = null;

        if ($this->request->cookies->has($cookieName)) {
            // The cart cookie exists -> get the cart token
            $token = $this->request->cookies->get($cookieName);

            Tlog::getInstance()->addDebug("Cookie $cookieName is in the request, value: $token");

            // Check if a cart exists for this token
            if (null !== $cart = CartQuery::create()->findOneByToken($token)) {
                Tlog::getInstance()->addDebug("Cart has been found in the database");

                if (null !== $customer = $this->session->getCustomerUser()) {
                    Tlog::getInstance()->addDebug("Customer " . $customer->getId() . " is logged in");

                    // A customer is logged in.
                    if ($cart->getCustomerId() != $customer->getId()) {
                        Tlog::getInstance()->addDebug("Cart customer (".$cart->getCustomerId().") <> current customer -> duplication");

                        // The cart does not belongs to the current customer
                        // -> clone it to create a new cart.
                        $cart = $this->duplicateCart(
                            $cartRestoreEvent->getDispatcher(),
                            $cart,
                            CustomerQuery::create()->findPk($customer->getId())
                        );
                    }
                } elseif ($cart->getCustomerId() != null) {
                    Tlog::getInstance()->addDebug("No customer logged in, but customer defined in cart (".$cart->getCustomerId().") -> duplication");

                    // Just duplicate the current cart, without assigning a customer ID.
                    $cart = $this->duplicateCart($cartRestoreEvent->getDispatcher(), $cart);
                }
            }
        }

        // Still no cart ? Create a new one.
        if (null === $cart) {
            Tlog::getInstance()->addDebug("No cart found, creating a new one.");

            $cartCreateEvent = new CartCreateEvent();

            $cartRestoreEvent->getDispatcher()->dispatch(TheliaEvents::CART_CREATE_NEW, $cartCreateEvent);

            $cart = $cartCreateEvent->getCart();
        }

        $cartRestoreEvent->setCart($cart);
    }

    /**
     * Create a new, empty cart object, and assign it to the current customer, if any.
     *
     * @param CartCreateEvent $cartCreateEvent
     */
    public function createEmptyCart(CartCreateEvent $cartCreateEvent)
    {
        $cart = new CartModel();

        $cart
            ->setToken($this->generateCartCookieIdentifier())
            ->setCurrency($this->session->getCurrency(true))
        ;

        if (null !== $customer = $this->session->getCustomerUser()) {
            $cart->setCustomer(CustomerQuery::create()->findPk($customer->getId()));
        }

        $cart->save();

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
            TheliaEvents::CART_RESTORE_CURRENT => array("restoreCurrentCart", 128),
            TheliaEvents::CART_CREATE_NEW => array("createEmptyCart", 128),
            TheliaEvents::CART_ADDITEM => array("addItem", 128),
            TheliaEvents::CART_DELETEITEM => array("deleteItem", 128),
            TheliaEvents::CART_UPDATEITEM => array("changeItem", 128),
            TheliaEvents::CART_CLEAR => array("clear", 128),
            TheliaEvents::CHANGE_DEFAULT_CURRENCY => array("updateCart", 128),
        );
    }
}
