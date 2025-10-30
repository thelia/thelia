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

namespace Thelia\Domain\Order;

use Propel\Runtime\Exception\PropelException;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Security\User\UserInterface;
use Thelia\Domain\Order\Service\OrderAddressPersister;
use Thelia\Domain\Order\Service\OrderFactory;
use Thelia\Domain\Order\Service\OrderProductFactory;
use Thelia\Domain\Order\Service\OrderTransactionManager;
use Thelia\Domain\Order\Service\StockPolicy;
use Thelia\Domain\Order\Service\TaxProvider;
use Thelia\Domain\Order\Service\TranslationProvider;
use Thelia\Domain\Order\Service\VirtualProductHandler;
use Thelia\Exception\TheliaProcessException;
use Thelia\Model\Cart as CartModel;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Currency as CurrencyModel;
use Thelia\Model\Lang as LangModel;
use Thelia\Model\Order as ModelOrder;
use Thelia\Model\OrderProductTax;
use Thelia\Model\OrderStatusQuery;

readonly class OrderFacade
{
    public function __construct(
        private OrderTransactionManager $orderTransactionManager,
        private OrderFactory $orderFactory,
        private OrderAddressPersister $orderAddressPersister,
        private TranslationProvider $translationProvider,
        private VirtualProductHandler $virtualProductHandler,
        private StockPolicy $stockPolicy,
        private TaxProvider $taxProvider,
        private OrderProductFactory $orderProductFactory,
    ) {
    }

    /**
     * @param bool $useOrderDefinedAddresses if true, the delivery and invoice OrderAddresses will be used instead of creating new OrderAdresses using Order::getChoosenXXXAddress()
     *
     * @throws \Exception
     * @throws PropelException
     */
    public function createOrder(
        EventDispatcherInterface $dispatcher,
        ModelOrder $sessionOrder,
        CurrencyModel $currency,
        LangModel $lang,
        CartModel $cart,
        UserInterface $customer,
        bool $useOrderDefinedAddresses = false,
    ): ModelOrder {
        if (null === $customer->getId()) {
            throw new TheliaProcessException('Customer identifier is required');
        }
        if (null === $currency->getId()) {
            throw new TheliaProcessException('Currency identifier is required');
        }
        if (null === $lang->getId()) {
            throw new TheliaProcessException('Language identifier is required');
        }
        if (null === $cart->getId()) {
            throw new TheliaProcessException('Cart identifier is required');
        }

        $connection = $this->orderTransactionManager->begin();

        try {
            $placedOrder = $this->orderFactory->createFromSessionOrder($sessionOrder, $currency, $lang, $cart, $customer);

            $taxCountry = $this->orderAddressPersister->prepareOrderAddresses(
                $placedOrder,
                $cart,
                $useOrderDefinedAddresses,
                $connection
            );

            $placedOrder->setStatusId(OrderStatusQuery::getNotPaidStatus()?->getId());
            $placedOrder->save($connection);

            $manageStockOnCreation = $placedOrder->isStockManagedOnOrderCreation($dispatcher);

            $cartItems = $cart->getCartItems();

            foreach ($cartItems as $cartItem) {
                $product = $cartItem->getProduct();
                $productSaleElements = $cartItem->getProductSaleElements();

                $productI18n = $this->translationProvider->getProductTranslation($lang->getLocale(), $product->getId());

                // Virtual Products
                $virtualContext = $this->virtualProductHandler->resolve(
                    $dispatcher,
                    $placedOrder,
                    $product,
                    $productSaleElements->getId()
                );

                // Stock check
                if ($this->stockPolicy->shouldCheckAvailability(ConfigQuery::checkAvailableStock(), $virtualContext->useStock)) {
                    $this->stockPolicy->assertStockIsAvailable(
                        $cartItem->getQuantity(),
                        $productSaleElements->getQuantity(),
                        'Not enough stock'
                    );
                }

                // Decrement stock
                if ($this->stockPolicy->shouldDecrementStock($manageStockOnCreation, $virtualContext->useStock)) {
                    $newQuantity = $this->stockPolicy->computeNewQuantity(
                        $productSaleElements->getQuantity(),
                        $cartItem->getQuantity(),
                        (int) ConfigQuery::read('allow_negative_stock', 0)
                    );

                    $productSaleElements->setQuantity($newQuantity);
                    $productSaleElements->save($connection);
                }

                // Taxes
                $taxRuleI18n = $this->translationProvider->getTaxRuleTranslation($lang->getLocale(), $product->getTaxRuleId());

                $taxDetails = $this->taxProvider->computeTaxesForCartItem(
                    $product,
                    $taxCountry,
                    (float) $cartItem->getPrice(),
                    (float) $cartItem->getPromoPrice(),
                    $lang->getLocale()
                );

                // Create OrderProduct + taxes + attributes
                $orderProduct = $this->orderProductFactory->createOrderProduct(
                    placedOrder: $placedOrder,
                    product: $product,
                    productSaleElements: $productSaleElements,
                    productI18n: $productI18n,
                    cartItem: $cartItem,
                    virtualContext: $virtualContext,
                    taxRuleI18n: $taxRuleI18n,
                    connection: $connection
                );

                /** @var OrderProductTax $tax */
                foreach ($taxDetails as $tax) {
                    $tax->setOrderProductId($orderProduct->getId());
                    $tax->save($connection);
                }

                $this->orderProductFactory->persistAttributeCombinations(
                    $orderProduct,
                    $productSaleElements,
                    $lang->getLocale(),
                    $connection
                );
            }

            $this->orderTransactionManager->commit($connection);

            return $placedOrder;
        } catch (\Throwable $throwable) {
            $this->orderTransactionManager->rollback($connection);
            throw $throwable;
        }
    }
}
