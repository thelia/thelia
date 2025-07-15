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

namespace Thelia\Core\Event\Order;

use Thelia\Model\Cart;
use Thelia\Model\Currency;
use Thelia\Model\Customer;
use Thelia\Model\Lang;
use Thelia\Model\Order;

class OrderManualEvent extends OrderEvent
{
    protected $currency;
    protected $lang;
    protected $cart;
    protected $customer;
    protected $useOrderDefinedAddresses = false;

    public function __construct(Order $order, Currency $currency, Lang $lang, Cart $cart, Customer $customer)
    {
        parent::__construct($order);

        $this
            ->setCurrency($currency)
            ->setLang($lang)
            ->setCart($cart)
            ->setCustomer($customer);
    }

    public function getCurrency()
    {
        return $this->currency;
    }

    /**
     * @return $this
     */
    public function setCurrency(Currency $currency): static
    {
        $this->currency = $currency;

        return $this;
    }

    public function getLang(): Lang
    {
        return $this->lang;
    }

    /**
     * @return $this
     */
    public function setLang(Lang $lang): static
    {
        $this->lang = $lang;

        return $this;
    }

    public function getCart(): Cart
    {
        return $this->cart;
    }

    /**
     * @return $this
     */
    public function setCart(Cart $cart): static
    {
        $this->cart = $cart;

        return $this;
    }

    public function getCustomer(): Customer
    {
        return $this->customer;
    }

    /**
     * @return $this
     */
    public function setCustomer(Customer $customer): static
    {
        $this->customer = $customer;

        return $this;
    }

    public function getUseOrderDefinedAddresses(): bool
    {
        return $this->useOrderDefinedAddresses;
    }

    /**
     * If true, the order will be created using the delivery and invoice addresses defined in $this->order instead of
     * creating new OrderAdresses using the Order::getChoosenXXXAddress().
     *
     * @return $this
     */
    public function setUseOrderDefinedAddresses(bool $useOrderDefinedAddresses): static
    {
        $this->useOrderDefinedAddresses = $useOrderDefinedAddresses;

        return $this;
    }
}
