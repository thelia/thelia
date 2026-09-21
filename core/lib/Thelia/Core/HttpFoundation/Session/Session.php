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

namespace Thelia\Core\HttpFoundation\Session;

use Symfony\Component\HttpFoundation\Session\Session as BaseSession;
use Symfony\Component\HttpFoundation\Session\Storage\SessionStorageInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Service\Attribute\Required;
use Thelia\Core\Event\Cart\CartCreateEvent;
use Thelia\Core\Event\Cart\CartRestoreEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\Security\User\UserInterface;
use Thelia\Domain\Localization\Service\LangService;
use Thelia\Model\Admin;
use Thelia\Model\Cart;
use Thelia\Model\CartQuery;
use Thelia\Model\Currency;
use Thelia\Model\Customer;
use Thelia\Model\Lang;
use Thelia\Model\Order;
use Thelia\Model\OrderQuery;
use Thelia\Tools\URL;

class Session extends BaseSession
{
    protected static ?Cart $transientCart = null;
    public const SESSION_CART_ID_NAME = 'thelia.cart_id';

    /**
     * Whether each cart read in this request has been paid for, with the order write
     * generation the answer was drawn at — see hasBeenPaidFor().
     *
     * @var array<int, array{int, bool}>
     */
    private array $paidForByCartId = [];

    /**
     * @deprecated the guest state is carried by customer.is_guest; nothing writes this
     *             key any more, and clearCustomerUser() only drops it from older sessions
     */
    public const SESSION_CUSTOMER_IS_GUEST = 'thelia.customer_is_guest';

    #[Required]
    public ?LangService $langService = null;

    public function getLang(bool $forceDefault = true): ?Lang
    {
        if (Request::$isAdminEnv) {
            return $this->getAdminLang();
        }

        $lang = $this->get('thelia.current.lang');

        if (null === $lang && $forceDefault) {
            $lang = Lang::getDefaultLanguage();
        }

        return $lang;
    }

    public function setLang(Lang $lang): static
    {
        $this->set('thelia.current.lang', $lang);

        return $this;
    }

    public function getAdminLang(): Lang
    {
        if (null !== $lang = $this->get('thelia.current.admin_lang')) {
            return $lang;
        }

        $adminUser = $this->getAdminUser();

        if ($adminUser instanceof Admin && null !== $this->langService) {
            $lang = $this->langService->resolveAdminLanguageFromAdmin($adminUser);
            $this->setAdminLang($lang);

            return $lang;
        }

        $lang = Lang::getDefaultLanguage();
        $this->setAdminLang($lang);

        return $lang;
    }

    public function setAdminLang(Lang $lang): static
    {
        $this->set('thelia.current.admin_lang', $lang);

        return $this;
    }

    public function setCurrency(Currency $currency): void
    {
        $this->set('thelia.current.currency', $currency);
    }

    public function getCurrency(bool $forceDefault = true): Currency
    {
        $currency = $this->get('thelia.current.currency');

        if (null === $currency && $forceDefault) {
            $currency = Currency::getDefaultCurrency();
        }

        return $currency;
    }

    public function getAdminEditionCurrency(): Currency
    {
        $currency = $this->get('thelia.admin.edition.currency', null);

        if (null === $currency) {
            $currency = Currency::getDefaultCurrency();
        }

        return $currency;
    }

    public function setAdminEditionCurrency(Currency $currency): static
    {
        $this->set('thelia.admin.edition.currency', $currency);

        return $this;
    }

    public function getAdminEditionLang(): Lang
    {
        $lang = $this->get('thelia.admin.edition.lang');

        if (null === $lang) {
            $lang = Lang::getDefaultLanguage();
        }

        return $lang;
    }

    public function setAdminEditionLang(Lang $lang): self
    {
        $this->set('thelia.admin.edition.lang', $lang);

        return $this;
    }

    public function setCustomerUser(?UserInterface $user): static
    {
        if (null !== $user) {
            $this->renewIdOnAuthentication($this->getCustomerUser(), $user);
        }

        $this->set('thelia.customer_user', $user);

        return $this;
    }

    public function getCustomerUser(): mixed
    {
        return $this->get('thelia.customer_user');
    }

    public function clearCustomerUser(): mixed
    {
        // Only to drop the key sessions created before the guest state moved onto the
        // customer row. Nothing writes it any more.
        $this->remove(self::SESSION_CUSTOMER_IS_GUEST);

        return $this->remove('thelia.customer_user');
    }

    /**
     * Kept so that callers written against the session flag still run. It does nothing.
     *
     * Whether the session customer is a guest is read off the row it points at, and the
     * row is the only thing entitled to say so: `customer.is_guest` stays 1 until the
     * activation code is answered. A flag anyone could set to false would take a guest
     * row straight into the account pages, which is exactly what it used to do.
     *
     * Sign a real customer in — TheliaEvents::CUSTOMER_LOGIN — and this answers false on
     * its own, because the session then points at an account.
     *
     * @deprecated the guest state is carried by customer.is_guest; this setter has no effect
     */
    public function setCustomerGuest(bool $isGuest): static
    {
        return $this;
    }

    /**
     * Whether the customer this session points at ordered without an account.
     */
    public function isCustomerGuest(): bool
    {
        $customer = $this->getCustomerUser();

        return $customer instanceof Customer && $customer->isGuest();
    }

    public function setAdminUser(UserInterface $user): static
    {
        $this->renewIdOnAuthentication($this->getAdminUser(), $user);
        $this->set('thelia.admin_user', $user);

        return $this;
    }

    /**
     * A session that authenticates someone new gets a new id: the id the browser carried
     * before the login was chosen before anyone was trusted, and may not have been chosen
     * by this browser at all. Refreshing the same user in place (revalidation on every
     * admin request) keeps the id, so parallel requests of that user stay on one session.
     */
    private function renewIdOnAuthentication(mixed $previous, UserInterface $user): void
    {
        if ($previous instanceof UserInterface && $previous->getId() === $user->getId()) {
            return;
        }

        if (!$this->isStarted()) {
            $this->start();
        }

        $this->migrate();
    }

    public function getAdminUser(): mixed
    {
        return $this->get('thelia.admin_user');
    }

    public function clearAdminUser(): mixed
    {
        return $this->remove('thelia.admin_user');
    }

    public function setReturnToUrl($url): static
    {
        $this->set('thelia.return_to_url', $url);

        return $this;
    }

    public function getReturnToUrl(): mixed
    {
        return $this->get('thelia.return_to_url', URL::getInstance()->getIndexPage());
    }

    public function setReturnToCatalogLastUrl($url): static
    {
        $this->set('thelia.return_to_catalog_last_url', $url);

        return $this;
    }

    public function getReturnToCatalogLastUrl(): mixed
    {
        return $this->get('thelia.return_to_catalog_last_url', URL::getInstance()->getIndexPage());
    }

    public function setSessionCart(?Cart $cart = null): self
    {
        if (!$cart instanceof Cart || $cart->isNew()) {
            self::$transientCart = $cart;
            $this->remove(self::SESSION_CART_ID_NAME);

            return $this;
        }
        self::$transientCart = null;
        $this->set(self::SESSION_CART_ID_NAME, $cart->getId());

        return $this;
    }

    /**
     * Will return the Cart stored in session,
     * try to restore if exists in context or create a new one if none is found (not persisted).
     */
    public function getSessionCart(EventDispatcherInterface $dispatcher): Cart
    {
        $cartId = $this->get(self::SESSION_CART_ID_NAME);
        $cart = null !== $cartId
            ? CartQuery::create()->findPk($cartId)
            : self::$transientCart;

        // A cart that has been deleted is no cart at all. The transient cart is held
        // in a static, so nothing invalidates it when the cart it points at goes:
        // handing it back gives the caller an object every save() on it rejects.
        if (null !== $cart && $cart->isDeleted()) {
            self::$transientCart = null;
            $this->remove(self::SESSION_CART_ID_NAME);
            $cart = null;
        }

        if (null !== $cart && $this->isValidCart($cart)) {
            return $this->hasBeenPaidFor($cart) ? $this->consume($cart, $dispatcher) : $cart;
        }
        $cartEvent = new CartRestoreEvent();

        if (null !== $cart) {
            $cartEvent->setCart($cart);
        }

        $dispatcher->dispatch($cartEvent, TheliaEvents::CART_RESTORE_CURRENT);
        if (null === $cart = $cartEvent->getCart()) {
            throw new \LogicException('Unable to get a Cart.');
        }

        // The persistent cookie restores a cart by its token, and that token may well
        // name a cart whose order was paid while the session was gone.
        if (!$cart->isNew() && $this->hasBeenPaidFor($cart)) {
            return $this->consume($cart, $dispatcher);
        }

        $this->setSessionCart($cart);

        return $cart;
    }

    /**
     * Whether an order placed from this cart has been paid, in the sense of the status
     * flow: paid, processing, sent or refunded, a status of the shop's own that stands
     * for one of them included.
     *
     * This is where the cart is consumed, rather than at the placement: it covers the
     * buyer coming back, the notification of the payment provider arriving on its own
     * and the tab closed on the payment page, without depending on any of them. One
     * query, on the index of `order.cart_id`, with the statuses hydrated alongside.
     *
     * The session cart is read several times in a request, and the answer only changes
     * when an order row is written: it is kept per cart for as long as no order has been
     * saved or deleted since, so a page costs one lookup, not one per read.
     */
    private function hasBeenPaidFor(Cart $cart): bool
    {
        $cartId = $cart->getId();

        if (null === $cartId) {
            return false;
        }

        $generation = Order::writeGeneration();
        $known = $this->paidForByCartId[$cartId] ?? null;

        if (null !== $known && $known[0] === $generation) {
            return $known[1];
        }

        $paid = $this->anOrderOfTheCartIsPaid($cartId);
        $this->paidForByCartId[$cartId] = [$generation, $paid];

        return $paid;
    }

    private function anOrderOfTheCartIsPaid(int $cartId): bool
    {
        $orders = OrderQuery::create()
            ->filterByCartId($cartId)
            ->joinWithOrderStatus()
            ->find();

        foreach ($orders as $order) {
            if ($order->isPaid(false) || $order->isRefunded(false)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Hands the session a new empty cart in place of one that has been paid for.
     *
     * The paid cart stays the row its order names, and is never handed back: the new
     * cart replaces it in the session and the persistent cookie is dropped with it.
     * A guest was put in the session only to carry that order through, and the order is
     * paid: leaving them there would hand the next person on this browser an identity
     * nobody signed into, so they are retired here, before the new cart is created with
     * nobody to attach it to.
     */
    private function consume(Cart $paidCart, EventDispatcherInterface $dispatcher): Cart
    {
        if ($this->isCustomerGuest()) {
            $this->clearCustomerUser();
        }

        $event = new CartCreateEvent();
        $dispatcher->dispatch($event, TheliaEvents::CART_CREATE_NEW);

        $newCart = $event->getCart() ?? throw new \LogicException('Unable to get a new empty Cart.');

        if ($newCart->getId() === $paidCart->getId()) {
            throw new \LogicException('The cart that replaces a paid cart cannot be the paid cart itself.');
        }

        return $newCart;
    }

    public function clearSessionCart(EventDispatcherInterface $dispatcher): void
    {
        $event = new CartCreateEvent();

        $dispatcher->dispatch($event, TheliaEvents::CART_CREATE_NEW);

        if (null === $event->getCart()) {
            throw new \LogicException('Unable to get a new empty Cart.');
        }
    }

    protected function isValidCart(Cart $cart): bool
    {
        $customer = $this->getCustomerUser();

        return (null !== $customer && $cart->getCustomerId() === $customer->getId())
            || null === $cart->getCustomerId();
    }

    public function setOrder(Order $order): static
    {
        $this->set('thelia.order', $order);

        return $this;
    }

    public function getOrder(): Order
    {
        $order = $this->get('thelia.order');

        if (null === $order) {
            $order = new Order();
            $this->setOrder($order);
        }

        return $order;
    }

    public function setConsumedCoupons(array $couponsCode): self
    {
        $this->set('thelia.consumed_coupons', $couponsCode);

        return $this;
    }

    public function getConsumedCoupons(): mixed
    {
        return $this->get('thelia.consumed_coupons', []);
    }

    public function getFormErrorInformation(): mixed
    {
        return $this->get('thelia.form-errors', []);
    }

    public function setFormErrorInformation(array $formInformation): static
    {
        $this->set('thelia.form-errors', $formInformation);

        return $this;
    }

    public function getStorage(): SessionStorageInterface
    {
        return $this->storage;
    }
}
