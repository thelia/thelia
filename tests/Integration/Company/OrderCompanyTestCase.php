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

namespace Thelia\Tests\Integration\Company;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Domain\Order\OrderFacade;
use Thelia\Model\Cart;
use Thelia\Model\CartQuery;
use Thelia\Model\Country;
use Thelia\Model\Currency;
use Thelia\Model\Customer;
use Thelia\Model\Lang;
use Thelia\Model\ModuleQuery;
use Thelia\Model\Order;
use Thelia\Model\OrderQuery;
use Thelia\Model\OrderStatus;
use Thelia\Model\OrderStatusQuery;

/**
 * What the tests of the orders of a company share: priced carts, orders placed the way the
 * checkout and the other callers place them, and orders already in the books.
 */
abstract class OrderCompanyTestCase extends CompanyDomainTestCase
{
    protected EventDispatcherInterface $dispatcher;

    private ?Country $country = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dispatcher = $this->getService(EventDispatcherInterface::class);
    }

    /**
     * A cart of one line, as the tunnel reads it once the carrier and the addresses are chosen:
     * taxes at zero, so that its total is the price and the postage.
     */
    protected function cartOf(Customer $customer, string $price, string $postage = '0', ?Currency $currency = null): Cart
    {
        $currency ??= $this->fixtures->currency();
        $cart = $this->fixtures->cart($customer, ['currency' => $currency]);
        $product = $this->fixtures->product(
            $this->fixtures->category(),
            $this->fixtures->taxRule(['isDefault' => false]),
            $currency,
            ['baseQuantity' => 100],
        );
        $this->fixtures->cartItem($cart, $product, null, ['price' => $price, 'promoPrice' => $price]);

        $cart
            ->setAddressDeliveryId($this->fixtures->cartAddress(null, $this->country())->getId())
            ->setAddressInvoiceId($this->fixtures->cartAddress(null, $this->country())->getId())
            ->setDeliveryModuleId(ModuleQuery::create()->findOneByCode('CustomDelivery')?->getId())
            ->setPostage($postage)
            ->setPostageTax('0')
            ->save($this->getPropelConnection());

        return CartQuery::create()->findPk($cart->getId()) ?? throw new \LogicException('The cart was just saved.');
    }

    /**
     * The order of a cart, written by the facade every caller goes through. Placed "at the
     * keyboard" is how the checkout of a theme and the front API place it; the others are the
     * back office and the installments of a subscription.
     */
    protected function place(Cart $cart, Customer $customer, bool $atTheKeyboard = true): Order
    {
        $currency = $cart->getCurrency() ?? $this->fixtures->currency();

        return $this->getService(OrderFacade::class)->createOrder(
            $this->dispatcher,
            $this->sessionOrder(),
            $currency,
            $this->fixtures->lang(),
            $cart,
            $customer,
            useOrderDefinedAddresses: true,
            recordConsentAnswers: $atTheKeyboard,
        );
    }

    protected function sessionOrder(string $postage = '0'): Order
    {
        return (new Order())
            ->setDeliveryOrderAddressId((int) $this->fixtures->orderAddress($this->country())->getId())
            ->setInvoiceOrderAddressId((int) $this->fixtures->orderAddress($this->country())->getId())
            ->setStatusId(OrderStatusQuery::getNotPaidStatus()?->getId())
            ->setDeliveryModuleId(ModuleQuery::create()->findOneByCode('CustomDelivery')?->getId())
            ->setPaymentModuleId(ModuleQuery::create()->findOneByCode('Cheque')?->getId())
            ->setPostage($postage)
            ->setPostageTax('0');
    }

    /**
     * An order already in the books, placed by the buyer for their company, of one line worth
     * the given amount.
     *
     * @param array<string, mixed> $overrides statusCode, currencyRate, postage, as {@see \Thelia\Test\FixtureFactory::order()} reads them
     */
    protected function orderInTheBooks(Customer $buyer, ?\Thelia\Model\Company $company, string $amount, array $overrides = [], ?\DateTimeInterface $createdAt = null): Order
    {
        $order = $this->fixtures->order($buyer, $overrides);
        $this->fixtures->orderProduct($order, ['price' => $amount, 'promoPrice' => $amount]);
        $order->setCompanyId($company?->getId())->setPlacedByCustomerId(null === $company ? null : $buyer->getId());

        if (null !== $createdAt) {
            $order->setCreatedAt(\DateTime::createFromInterface($createdAt));
        }

        $order->save($this->getPropelConnection());

        return OrderQuery::create()->findPk($order->getId()) ?? $order;
    }

    protected function moveTo(Order $order, string $code): void
    {
        $order->setStatusId(OrderStatusQuery::create()->filterByCode($code)->findOne()?->getId())->save($this->getPropelConnection());
    }

    protected function countOfOrdersOf(Customer $customer): int
    {
        return OrderQuery::create()->filterByCustomerId($customer->getId())->count();
    }

    protected function lang(): Lang
    {
        return $this->fixtures->lang();
    }

    private function country(): Country
    {
        return $this->country ??= $this->fixtures->country();
    }

    protected static function cancelled(): string
    {
        return OrderStatus::CODE_CANCELED;
    }
}
