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
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Cart\CartCreateEvent;
use Thelia\Core\Event\Cart\CartRestoreEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\Security\User\UserInterface;
use Thelia\Model\Admin;
use Thelia\Model\Cart;
use Thelia\Model\CartQuery;
use Thelia\Model\Currency;
use Thelia\Model\Lang;
use Thelia\Model\LangQuery;
use Thelia\Model\Order;
use Thelia\Tools\URL;

class Session extends BaseSession
{
    protected static ?Cart $transientCart = null;

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

        if (
            $adminUser instanceof Admin
            && $lang = LangQuery::create()->findOneByLocale($adminUser->getLocale())
        ) {
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

    // -- Customer user --------------------------------------------------------

    public function setCustomerUser(?UserInterface $user): static
    {
        $this->set('thelia.customer_user', $user);

        return $this;
    }

    public function getCustomerUser(): mixed
    {
        return $this->get('thelia.customer_user');
    }

    public function clearCustomerUser(): mixed
    {
        return $this->remove('thelia.customer_user');
    }

    public function setAdminUser(UserInterface $user): static
    {
        $this->set('thelia.admin_user', $user);

        return $this;
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
            $this->remove('thelia.cart_id');
        } else {
            self::$transientCart = null;
            $this->set('thelia.cart_id', $cart->getId());
        }

        return $this;
    }

    public function getSessionCart(?EventDispatcherInterface $dispatcher = null): ?Cart
    {
        $cart_id = $this->get('thelia.cart_id', null);
        $cart = null !== $cart_id ? CartQuery::create()->findPk($cart_id) : self::$transientCart;

        // If we do not have a cart, or if the current cart is nor valid
        // restore it from the cart cookie, or create a new one
        if (null === $cart || !$this->isValidCart($cart)) {
            // A dispatcher is required here. If we do not have it, throw an exception
            // This is a temporary workaround to ensure backward compatibility with getCart(),
            // When genCart() will be removed, this check should be removed, and  $dispatcher should become
            // a required parameter.

            if (!$dispatcher instanceof EventDispatcherInterface) {
                throw new \InvalidArgumentException('In this context (no cart in session), an EventDispatcher should be provided to Session::getSessionCart().');
            }

            $cartEvent = new CartRestoreEvent();

            if (null !== $cart) {
                $cartEvent->setCart($cart);
            }

            $dispatcher->dispatch($cartEvent, TheliaEvents::CART_RESTORE_CURRENT);

            $cart = $cartEvent->getCart();

            throw new \LogicException('Unable to get a Cart.');
        }

        return $cart;
    }

    public function clearSessionCart(EventDispatcherInterface $dispatcher): void
    {
        $event = new CartCreateEvent();

        $dispatcher->dispatch($event, TheliaEvents::CART_CREATE_NEW);

        throw new \LogicException('Unable to get a new empty Cart.');
    }

    protected function isValidCart(Cart $cart): bool
    {
        $customer = $this->getCustomerUser();

        return (null !== $customer && $cart->getCustomerId() === $customer->getId())
        || (null === $customer && null === $cart->getCustomerId());
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
}
