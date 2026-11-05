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

namespace Thelia\Api\Bridge\Propel\Filter\CustomFilters\Filters;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\ActiveRecord\ActiveRecordInterface;
use Thelia\Api\Bridge\Propel\Filter\CustomFilters\Filters\Interface\TheliaAggregatedFilterInterface;
use Thelia\Api\Bridge\Propel\Filter\CustomFilters\Filters\Interface\TheliaFilterInterface;
use Thelia\Api\Resource\FilterValue;
use Thelia\Core\Translation\Translator;
use Thelia\Domain\Catalog\Stock\OutOfStockPolicy;
use Thelia\Domain\Catalog\Stock\StockSettings;
use Thelia\Domain\Catalog\Stock\VisitorCart;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Product;
use Thelia\Model\ProductQuery;

/**
 * Splits a listing between the products a buyer can have now and the others.
 *
 * A product is in stock when it is virtual, or when one of its visible combinations has a
 * positive quantity: a buyer picks the size he wants, so one available size is enough, the
 * rule the promotion facet already follows. Everything else is on order or out of stock,
 * a product without any combination included, so that it stays counted somewhere.
 *
 * When the shop does not check stock (`check-available-stock` off), every product can be
 * ordered whatever its quantity, and the whole listing is in stock.
 */
final readonly class AvailabilityFilter implements TheliaFilterInterface, TheliaAggregatedFilterInterface
{
    use SelectedValuesTrait;

    public const IN_STOCK = 1;

    public const OUT_OF_STOCK = 2;

    public function __construct(private Translator $translator, private ?StockSettings $stockSettings = null, private ?VisitorCart $visitorCart = null)
    {
    }

    public function getResourceType(): array
    {
        return ['products'];
    }

    public static function getFilterName(): array
    {
        return ['availability'];
    }

    public function filter(ModelCriteria $query, $value, bool $isMinOrMaxFilter = false, ?int $categoryDepth = null): void
    {
        $selected = array_values(array_intersect(
            [self::IN_STOCK, self::OUT_OF_STOCK],
            array_map('intval', array_filter($this->flattenSelectedValues($value), 'is_numeric')),
        ));

        // Both values together keep every product, and no value is no selection at all.
        if (\count($selected) !== 1) {
            return;
        }

        if (!ConfigQuery::checkAvailableStock()) {
            if ($selected[0] === self::OUT_OF_STOCK) {
                $query->where('1 <> 1');
            }

            return;
        }

        if ($selected[0] === self::IN_STOCK) {
            $this->restrictToInStock($query);

            return;
        }

        $this->restrictToOutOfStock($query);
    }

    public function getValue(ActiveRecordInterface $activeRecord, string $locale, $valueSearched = null, ?int $depth = 1): ?array
    {
        if (!$activeRecord instanceof Product) {
            return null;
        }

        $inStock = !ConfigQuery::checkAvailableStock()
            || $this->inStockProducts()->filterById($activeRecord->getId())->exists();

        return [$this->value($inStock ? self::IN_STOCK : self::OUT_OF_STOCK, $locale)];
    }

    public function getAggregatedValues(array $resourceIds, string $locale, $valueSearched = null, ?int $depth = 1): array
    {
        if ($resourceIds === []) {
            return [];
        }

        $total = \count(array_unique($resourceIds));
        $inStock = $total;

        if (ConfigQuery::checkAvailableStock()) {
            $inStock = \count($this->inStockProducts()
                ->filterById($resourceIds, Criteria::IN)
                ->select(['Id'])
                ->find()
                ->getData());
        }

        // A value no product holds is not offered: it would narrow the listing to nothing.
        $values = [];

        if ($inStock > 0) {
            $values[] = $this->value(self::IN_STOCK, $locale)->setCount($inStock);
        }

        if ($total - $inStock > 0) {
            $values[] = $this->value(self::OUT_OF_STOCK, $locale)->setCount($total - $inStock);
        }

        return $values;
    }

    private function inStockProducts(): ProductQuery
    {
        $query = ProductQuery::create();
        $this->restrictToInStock($query);

        return $query;
    }

    /**
     * The OR binds to the criterion added just before it, which is why the two halves of the
     * rule are written back to back. Each half joins the combinations under its own alias, so
     * the price ordering and the flag facets, which join the same table, are left alone.
     */
    private function restrictToInStock(ModelCriteria $query): void
    {
        if ($this->reservesStock()) {
            $query
                ->filterBy('Virtual', 1)
                ->_or()
                ->useExistsQuery('ProductSaleElements', 'availability_in_stock_pse')
                    ->filterBy('Visible', true)
                    ->where($this->sellableAfterReservationsClause('availability_in_stock_pse'))
                ->endUse();

            return;
        }

        $query
            ->filterBy('Virtual', 1)
            ->_or()
            ->useExistsQuery('ProductSaleElements', 'availability_in_stock_pse')
                ->filterByVisible(true)
                ->filterByQuantity(0, Criteria::GREATER_THAN)
                ->_or()
                ->where(self::sellsBelowZeroClause('availability_in_stock_pse'))
            ->endUse();
    }

    private function restrictToOutOfStock(ModelCriteria $query): void
    {
        if ($this->reservesStock()) {
            $query
                ->filterBy('Virtual', 0)
                ->useNotExistsQuery('ProductSaleElements', 'availability_out_of_stock_pse')
                    ->filterBy('Visible', true)
                    ->where($this->sellableAfterReservationsClause('availability_out_of_stock_pse'))
                ->endUse();

            return;
        }

        $query
            ->filterBy('Virtual', 0)
            ->useNotExistsQuery('ProductSaleElements', 'availability_out_of_stock_pse')
                ->filterByVisible(true)
                ->filterByQuantity(0, Criteria::GREATER_THAN)
                ->_or()
                ->where(self::sellsBelowZeroClause('availability_out_of_stock_pse'))
            ->endUse();
    }

    /**
     * A sale element out of stock that is still open for sale: its sale mode sells without limit, or
     * sells below zero and its preorder limit is not used up (the stock plus the limit is above zero).
     * Reservations of other carts are not looked at: a facet is a count over the catalogue, not an
     * answer for a cart.
     */
    private static function sellsBelowZeroClause(string $alias): string
    {
        return \sprintf(
            '%1$s.out_of_stock_policy = %2$d OR (%1$s.out_of_stock_policy = %3$d AND %1$s.quantity + GREATEST(COALESCE(%1$s.preorder_limit, 0), 0) > 0)',
            $alias,
            OutOfStockPolicy::Unlimited->value,
            OutOfStockPolicy::Limited->value,
        );
    }

    private function reservesStock(): bool
    {
        return null !== $this->stockSettings && $this->stockSettings->reservationEnabled();
    }

    /**
     * When the shop reserves its stock: a sale element is still for sale when its sale mode sells
     * without limit, or when its stock (plus its preorder limit when it sells below zero) is above
     * what the carts hold of it right now. The cart of the visitor does not count, as everywhere else,
     * when it is known without opening a session ({@see VisitorCart}: a started session, a guest or
     * customer token); a visitor whose session is not started has no cart here, and every cart counts.
     * The sale unit of the cart is not looked at: a facet is a count over the catalogue, not an answer
     * for a cart, so a reservation on the own stock of another sale unit is still counted here.
     */
    private function sellableAfterReservationsClause(string $alias): string
    {
        $ownCartId = $this->visitorCart?->currentId();
        $reserved = \sprintf(
            '(SELECT COALESCE(SUM(`reserving_line`.`quantity`), 0) FROM `cart_item` `reserving_line` WHERE `reserving_line`.`product_sale_elements_id` = %s.id AND `reserving_line`.`reserved_until` > NOW()%s)',
            $alias,
            null === $ownCartId ? '' : \sprintf(' AND `reserving_line`.`cart_id` <> %d', $ownCartId),
        );

        return \sprintf(
            '(%1$s.out_of_stock_policy = %2$d OR %1$s.quantity + (CASE WHEN %1$s.out_of_stock_policy = %3$d THEN GREATEST(COALESCE(%1$s.preorder_limit, 0), 0) ELSE 0 END) - %4$s > 0)',
            $alias,
            OutOfStockPolicy::Unlimited->value,
            OutOfStockPolicy::Limited->value,
            $reserved,
        );
    }

    private function value(int $id, string $locale): FilterValue
    {
        return (new FilterValue())
            ->setId($id)
            ->setTitle($this->translator->trans(
                id: $id === self::IN_STOCK ? 'In stock' : 'On order or out of stock',
                locale: $locale,
            ));
    }
}
