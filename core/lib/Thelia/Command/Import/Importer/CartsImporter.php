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

use Thelia\Command\Import\AbstractDemoImporter;
use Thelia\Command\Import\DemoImportContext;
use Thelia\Model\Cart;
use Thelia\Model\CartItem;
use Thelia\Model\CurrencyQuery;
use Thelia\Model\ProductPriceQuery;
use Thelia\Model\ProductSaleElementsQuery;

/**
 * Seeds abandoned carts (carts with items but no order) to populate the
 * dashboard's abandoned-cart metrics. Some are tied to a customer, some
 * anonymous, all created within the last few weeks.
 */
final class CartsImporter extends AbstractDemoImporter
{
    private const ABANDONED_CART_COUNT = 10;

    public function priority(): int
    {
        return 130;
    }

    public function description(): string
    {
        return 'Abandoned carts';
    }

    public function import(DemoImportContext $context): void
    {
        $currency = CurrencyQuery::create()->filterByByDefault(1)->findOne($context->connection)
            ?? CurrencyQuery::create()->findOne($context->connection);
        if (null === $currency) {
            return;
        }

        $entries = $this->buildEntries($context, (int) $currency->getId());
        if ([] === $entries) {
            return;
        }

        $customerCount = \count($context->customers);
        $entryCount = \count($entries);

        for ($i = 0; $i < self::ABANDONED_CART_COUNT; ++$i) {
            $cart = new Cart();
            if ($customerCount > 0 && 0 !== $i % 3) {
                $cart->setCustomerId((int) $context->customers[$i % $customerCount]->getId());
            }
            $cart->setCurrencyId((int) $currency->getId());
            $cart->setToken('demo-abandoned-cart-'.$i);
            $cart->setCreatedAt((new \DateTime())->modify(\sprintf('-%d days', $i * 2 + 1)));
            $cart->save($context->connection);

            $itemCount = 1 + ($i % 3);
            for ($j = 0; $j < $itemCount; ++$j) {
                $entry = $entries[($i * 2 + $j) % $entryCount];

                $cartItem = new CartItem();
                $cartItem->setCartId((int) $cart->getId());
                $cartItem->setProductId($entry['productId']);
                $cartItem->setProductSaleElementsId($entry['pseId']);
                $cartItem->setQuantity((float) (1 + ($j % 2)));
                $cartItem->setPrice($entry['price']);
                $cartItem->setPromoPrice('0');
                $cartItem->save($context->connection);
            }
        }
    }

    /**
     * @return list<array{productId: int, pseId: int, price: string}>
     */
    private function buildEntries(DemoImportContext $context, int $currencyId): array
    {
        $entries = [];
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

            $entries[] = [
                'productId' => (int) $product->getId(),
                'pseId' => (int) $pse->getId(),
                'price' => $price->getPrice(),
            ];
        }

        return $entries;
    }
}
