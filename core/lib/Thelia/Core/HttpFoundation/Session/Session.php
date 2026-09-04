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
use Thelia\Tools\URL;

class Session extends BaseSession
{
    protected static ?Cart $transientCart = null;
    public const SESSION_CART_ID_NAME = 'thelia.cart_id';

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

        if (null !== $cart && $this->isValidCart($cart)) {
            return $cart;
        }
        $cartEvent = new CartRestoreEvent();

        if (null !== $cart) {
            $cartEvent->setCart($cart);
        }

        $dispatcher->dispatch($cartEvent, TheliaEvents::CART_RESTORE_CURRENT);
        if (null === $cart = $cartEvent->getCart()) {
            throw new \LogicException('Unable to get a Cart.');
        }

        $this->setSessionCart($cart);

        return $cart;
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
