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

namespace Thelia\Command\Import\Importer;

use Propel\Runtime\Connection\ConnectionInterface;
use Thelia\Command\Import\AbstractDemoImporter;
use Thelia\Command\Import\DemoImportContext;
use Thelia\Model\Address;
use Thelia\Model\AddressQuery;
use Thelia\Model\Cart;
use Thelia\Model\Currency;
use Thelia\Model\CurrencyQuery;
use Thelia\Model\Customer;
use Thelia\Model\Lang;
use Thelia\Model\LangQuery;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;
use Thelia\Model\Order;
use Thelia\Model\OrderAddress;
use Thelia\Model\OrderProduct;
use Thelia\Model\OrderProductTax;
use Thelia\Model\OrderStatus;
use Thelia\Model\OrderStatusQuery;
use Thelia\Model\ProductPriceQuery;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Module\BaseModule;

/**
 * Seeds back-dated demo orders so the dashboard, order list and invoices have
 * realistic data. Orders are spread over the last twelve months with a status
 * mix that reflects their age (older ones shipped, recent ones still pending).
 */
final class OrdersImporter extends AbstractDemoImporter
{
    private const VAT_RATE = 0.20;

    /**
     * Number of orders per month, most recent month first. Growing toward the
     * present mimics a healthy store and fills the dashboard charts.
     */
    private const ORDERS_PER_MONTH = [12, 10, 8, 7, 5, 5, 4, 4, 3, 3, 2, 2];

    public function priority(): int
    {
        return 120;
    }

    public function description(): string
    {
        return 'Orders';
    }

    public function import(DemoImportContext $context): void
    {
        if ([] === $context->customers || [] === $context->products) {
            return;
        }

        $payment = $this->resolveModule($context, BaseModule::PAYMENT_MODULE_TYPE, 'Cheque');
        $delivery = $this->resolveModule($context, BaseModule::DELIVERY_MODULE_TYPE, 'CustomDelivery');
        $currency = CurrencyQuery::create()->filterByByDefault(1)->findOne($context->connection)
            ?? CurrencyQuery::create()->findOne($context->connection);
        $lang = LangQuery::create()->filterByByDefault(1)->findOne($context->connection)
            ?? LangQuery::create()->findOne($context->connection);

        if (null === $payment || null === $delivery || null === $currency || null === $lang) {
            $context->output->writeln('<comment>Missing module, currency or lang — skipping demo orders</comment>');

            return;
        }

        $catalog = $this->buildPriceCatalog($context, (int) $currency->getId());
        if ([] === $catalog) {
            return;
        }

        $statuses = $this->loadStatuses($context);
        $customerCount = \count($context->customers);
        $catalogCount = \count($catalog);

        $index = 0;
        foreach (self::ORDERS_PER_MONTH as $monthsAgo => $count) {
            for ($slot = 0; $slot < $count; ++$slot) {
                $customer = $context->customers[$index % $customerCount];
                $statusCode = $this->pickStatusCode($monthsAgo, $index);

                $order = $this->createOrder(
                    $context->connection,
                    $customer,
                    $statuses[$statusCode],
                    $statusCode,
                    $payment,
                    $delivery,
                    $currency,
                    $lang,
                    $this->orderDate($monthsAgo, $slot),
                );

                $lineCount = 1 + ($index % 4);
                for ($line = 0; $line < $lineCount; ++$line) {
                    $item = $catalog[($index * 3 + $line) % $catalogCount];
                    $this->addOrderProduct($context->connection, $order, $item, 1 + (($index + $line) % 3));
                }

                ++$index;
            }
        }
    }

    private function resolveModule(DemoImportContext $context, int $type, string $preferredCode): ?Module
    {
        // Prefer a realistic named module (e.g. Cheque over FreeOrder), then
        // fall back to any active module of the right type.
        return ModuleQuery::create()->filterByActivate(1)->filterByType($type)->filterByCode($preferredCode)->findOne($context->connection)
            ?? ModuleQuery::create()->filterByActivate(1)->filterByType($type)->orderByPosition()->findOne($context->connection);
    }

    /**
     * @return list<array{ref: string, pseRef: string, pseId: int, price: string, title: string}>
     */
    private function buildPriceCatalog(DemoImportContext $context, int $currencyId): array
    {
        $catalog = [];
        foreach ($context->products as $product) {
            $pse = ProductSaleElementsQuery::create()->filterByProductId($product->getId())->filterByIsDefault(1)->findOne($context->connection)
                ?? ProductSaleElementsQuery::create()->filterByProductId($product->getId())->findOne($context->connection);
            if (null === $pse) {
                continue;
            }

            $price = ProductPriceQuery::create()
                ->filterByProductSaleElementsId($pse->getId())
                ->filterByCurrencyId($currencyId)
                ->findOne($context->connection);
            if (null === $price) {
                continue;
            }

            $catalog[] = [
                'ref' => (string) $product->getRef(),
                'pseRef' => (string) $pse->getRef(),
                'pseId' => (int) $pse->getId(),
                'price' => $price->getPrice(),
                'title' => (string) $product->setLocale('en_US')->getTitle(),
            ];
        }

        return $catalog;
    }

    /**
     * @return array<string, OrderStatus>
     */
    private function loadStatuses(DemoImportContext $context): array
    {
        $statuses = [];
        foreach (OrderStatusQuery::create()->find($context->connection) as $status) {
            $statuses[(string) $status->getCode()] = $status;
        }

        return $statuses;
    }

    private function pickStatusCode(int $monthsAgo, int $index): string
    {
        if ($monthsAgo >= 2) {
            return match ($index % 10) {
                0 => OrderStatus::CODE_CANCELED,
                1 => OrderStatus::CODE_REFUNDED,
                default => OrderStatus::CODE_SENT,
            };
        }

        if (1 === $monthsAgo) {
            return 0 === $index % 3 ? OrderStatus::CODE_PAID : OrderStatus::CODE_SENT;
        }

        return match ($index % 3) {
            0 => OrderStatus::CODE_PAID,
            1 => OrderStatus::CODE_PROCESSING,
            default => OrderStatus::CODE_NOT_PAID,
        };
    }

    private function orderDate(int $monthsAgo, int $slot): \DateTime
    {
        $daysAgo = $monthsAgo * 30 + (($slot * 7 + 2) % 28);
        $date = new \DateTime(\sprintf('-%d days', $daysAgo));
        $date->setTime(10 + ($slot % 9), ($slot * 13) % 60, 0);

        return $date;
    }

    private function createOrder(
        ConnectionInterface $connection,
        Customer $customer,
        OrderStatus $status,
        string $statusCode,
        Module $payment,
        Module $delivery,
        Currency $currency,
        Lang $lang,
        \DateTime $createdAt,
    ): Order {
        $address = AddressQuery::create()->filterByCustomerId($customer->getId())->filterByIsDefault(1)->findOne($connection)
            ?? AddressQuery::create()->filterByCustomerId($customer->getId())->findOne($connection);

        $cart = new Cart();
        $cart->setCustomerId((int) $customer->getId());
        $cart->setCurrencyId((int) $currency->getId());
        $cart->setToken('demo-order-cart-'.$customer->getId().'-'.$createdAt->getTimestamp());
        $cart->save($connection);

        $order = new Order();
        $order->setCustomer($customer);
        $order->setInvoiceOrderAddressId($this->createOrderAddress($connection, $customer, $address)->getId());
        $order->setDeliveryOrderAddressId($this->createOrderAddress($connection, $customer, $address)->getId());
        $order->setCurrencyId((int) $currency->getId());
        $order->setCurrencyRate(1.0);
        $order->setPaymentModuleId((int) $payment->getId());
        $order->setDeliveryModuleId((int) $delivery->getId());
        $order->setStatusId((int) $status->getId());
        $order->setLangId((int) $lang->getId());
        $order->setCartId((int) $cart->getId());
        $order->setPostage('0');
        $order->setPostageTax('0');
        $order->setCreatedAt($createdAt);

        if (\in_array($statusCode, [OrderStatus::CODE_PAID, OrderStatus::CODE_PROCESSING, OrderStatus::CODE_SENT, OrderStatus::CODE_REFUNDED], true)) {
            $order->setInvoiceDate($createdAt);
        }

        $order->save($connection);

        return $order;
    }

    private function createOrderAddress(ConnectionInterface $connection, Customer $customer, ?Address $address): OrderAddress
    {
        $orderAddress = new OrderAddress();
        $orderAddress->setCustomerTitleId($address?->getTitleId() ?? $customer->getTitleId());
        $orderAddress->setFirstname((string) $customer->getFirstname());
        $orderAddress->setLastname((string) $customer->getLastname());
        $orderAddress->setAddress1($address?->getAddress1() ?? '');
        $orderAddress->setAddress2('');
        $orderAddress->setAddress3('');
        $orderAddress->setZipcode($address?->getZipcode() ?? '');
        $orderAddress->setCity($address?->getCity() ?? '');
        $orderAddress->setCountryId($address?->getCountryId() ?? 64);
        $orderAddress->save($connection);

        return $orderAddress;
    }

    /**
     * @param array{ref: string, pseRef: string, pseId: int, price: string, title: string} $item
     */
    private function addOrderProduct(ConnectionInterface $connection, Order $order, array $item, int $quantity): void
    {
        $orderProduct = new OrderProduct();
        $orderProduct->setOrderId((int) $order->getId());
        $orderProduct->setProductRef($item['ref']);
        $orderProduct->setProductSaleElementsRef($item['pseRef']);
        $orderProduct->setProductSaleElementsId($item['pseId']);
        $orderProduct->setTitle($item['title']);
        $orderProduct->setQuantity((float) $quantity);
        $orderProduct->setPrice($item['price']);
        $orderProduct->setPromoPrice('0');
        $orderProduct->setWasNew(0);
        $orderProduct->setWasInPromo(0);
        $orderProduct->save($connection);

        $taxAmount = round((float) $item['price'] * self::VAT_RATE, 2);

        $orderProductTax = new OrderProductTax();
        $orderProductTax->setOrderProductId((int) $orderProduct->getId());
        $orderProductTax->setTitle('VAT 20%');
        $orderProductTax->setAmount((string) $taxAmount);
        $orderProductTax->setPromoAmount('0');
        $orderProductTax->save($connection);
    }
}
