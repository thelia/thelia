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

namespace Thelia\Core\Template\Loop;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Thelia\Core\Template\Element\BaseLoop;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\LoopResultRow;
use Thelia\Core\Template\Element\PropelSearchLoopInterface;
use Thelia\Core\Template\Element\SearchLoopInterface;
use Thelia\Core\Template\Loop\Argument\Argument;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Domain\Catalog\Product\VisitorPriceReader;
use Thelia\Domain\Pricing\CurrencyConverter;
use Thelia\Domain\Pricing\CustomerPrice\ResolvedPrice;
use Thelia\Domain\Sale\ReservedSaleVisibility;
use Thelia\Domain\SaleUnit\Stock\SaleUnitAvailableStock;
use Thelia\Domain\Taxation\TaxEngine\Exception\TaxEngineException;
use Thelia\Domain\Taxation\TaxEngine\TaxEngine;
use Thelia\Model\Currency as CurrencyModel;
use Thelia\Model\CurrencyQuery;
use Thelia\Model\Map\ProductSaleElementsTableMap;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Type\BooleanOrBothType;
use Thelia\Type\EnumListType;
use Thelia\Type\IntToCombinedIntsListType;
use Thelia\Type\TypeCollection;

/**
 * Product Sale Elements loop.
 *
 * @todo : manage attribute_availability ?
 *
 * Class ProductSaleElements
 *
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 *
 * @method int[]       getId()
 * @method int         getCurrency()
 * @method int         getProduct()
 * @method bool        getPromo()
 * @method bool        getNew()
 * @method bool        getDefault()
 * @method string      getRef()
 * @method int[]       getAttributeAvailability()
 * @method string[]    getOrder()
 * @method bool|string getVisible()
 */
class ProductSaleElements extends BaseLoop implements PropelSearchLoopInterface, SearchLoopInterface
{
    protected $timestampable = true;

    private ?CurrencyModel $currency = null;

    public function __construct(
        protected readonly TaxEngine $taxEngine,
        protected readonly ReservedSaleVisibility $reservedSaleVisibility,
        protected readonly VisitorPriceReader $visitorPriceReader,
        protected readonly CurrencyConverter $currencyConverter,
        protected readonly ?SaleUnitAvailableStock $saleUnitAvailableStock = null,
    ) {
    }

    protected function getArgDefinitions(): ArgumentCollection
    {
        return new ArgumentCollection(
            Argument::createIntListTypeArgument('id'),
            Argument::createIntTypeArgument('currency'),
            Argument::createIntTypeArgument('product'),
            Argument::createBooleanTypeArgument('promo'),
            Argument::createBooleanTypeArgument('new'),
            Argument::createBooleanTypeArgument('default'),
            Argument::createBooleanOrBothTypeArgument('visible', BooleanOrBothType::ANY),
            Argument::createAnyTypeArgument('ref'),
            new Argument(
                'attribute_availability',
                new TypeCollection(
                    new IntToCombinedIntsListType(),
                ),
            ),
            new Argument(
                'order',
                new TypeCollection(
                    new EnumListType(
                        [
                            'id', 'id_reverse',
                            'ref', 'ref_reverse',
                            'quantity', 'quantity_reverse',
                            'min_price', 'max_price',
                            'promo', 'new',
                            'weight', 'weight_reverse',
                            'created', 'created_reverse',
                            'updated', 'updated_reverse',
                            'random',
                            'position',
                            'position-reverse',
                        ],
                    ),
                ),
                'position',
            ),
        );
    }

    public function buildModelCriteria(): ModelCriteria
    {
        $search = ProductSaleElementsQuery::create();

        // A private drop hides its products from the front, the back office
        // included in nothing: an administrator setting it up has to see what is
        // in it.
        if (!$this->getBackendContext()) {
            $this->reservedSaleVisibility->applyTo($search, ProductSaleElementsTableMap::COL_PRODUCT_ID);
        }

        $id = $this->getId();
        $product = $this->getProduct();
        $ref = $this->getRef();

        if (null !== $id) {
            $search->filterById($id, Criteria::IN);
        }

        if (null !== $product) {
            $search->filterByProductId($product, Criteria::EQUAL);
        }

        if (null !== $ref) {
            $search->filterByRef($ref, Criteria::EQUAL);
        }

        $promo = $this->getPromo();

        if (null !== $promo) {
            $search->filterByPromo($promo);
        }

        $new = $this->getNew();

        if (null !== $new) {
            $search->filterByNewness($new);
        }

        $visible = $this->getVisible();

        if (BooleanOrBothType::ANY !== $visible) {
            $search->filterByVisible($visible);
        }

        $default = $this->getDefault();

        if (null !== $default) {
            $search->filterByIsDefault($default);
        }

        $orders = $this->getOrder();

        foreach ($orders as $order) {
            switch ($order) {
                case 'id':
                    $search->orderById(Criteria::ASC);
                    break;
                case 'id_reverse':
                    $search->orderById(Criteria::DESC);
                    break;
                case 'ref':
                    $search->orderByRef(Criteria::ASC);
                    break;
                case 'ref_reverse':
                    $search->orderByRef(Criteria::DESC);
                    break;
                case 'quantity':
                    $search->orderByQuantity(Criteria::ASC);
                    break;
                case 'quantity_reverse':
                    $search->orderByQuantity(Criteria::DESC);
                    break;
                case 'min_price':
                    $search->addAscendingOrderByColumn('price_FINAL_PRICE');
                    break;
                case 'max_price':
                    $search->addDescendingOrderByColumn('price_FINAL_PRICE');
                    break;
                case 'promo':
                    $search->orderByPromo(Criteria::DESC);
                    break;
                case 'new':
                    $search->orderByNewness(Criteria::DESC);
                    break;
                case 'weight':
                    $search->orderByWeight(Criteria::ASC);
                    break;
                case 'weight_reverse':
                    $search->orderByWeight(Criteria::DESC);
                    break;
                case 'created':
                    $search->addAscendingOrderByColumn('created_at');
                    break;
                case 'created_reverse':
                    $search->addDescendingOrderByColumn('created_at');
                    break;
                case 'updated':
                    $search->addAscendingOrderByColumn('updated_at');
                    break;
                case 'updated_reverse':
                    $search->addDescendingOrderByColumn('updated_at');
                    break;
                case 'position':
                    $search->orderByPosition();
                    break;
                case 'position_reverse':
                    $search->orderByPosition(Criteria::DESC);
                    break;
                case 'random':
                    $search->clearOrderByColumns();
                    $search->addAscendingOrderByColumn('RAND()');
                    break 2;
            }
        }

        $currencyId = $this->getCurrency();

        if (null !== $currencyId) {
            $currency = CurrencyQuery::create()->findPk($currencyId);

            if (null === $currency) {
                throw new \InvalidArgumentException('Cannot found currency id: `'.$currency.'` in product_sale_elements loop');
            }
        } else {
            $currency = $this->getMainRequest()->getSession()->getCurrency();
        }

        // parseResults() prices the rows in the same currency the query selected
        // them in, and has no argument of its own to read it from.
        $this->currency = $currency;

        $defaultCurrency = CurrencyModel::getDefaultCurrency();
        $defaultCurrencySuffix = '_default_currency';

        $search->joinProductPrice('price', Criteria::LEFT_JOIN)
            ->addJoinCondition('price', '`price`.`currency_id` = ?', $currency->getId(), null, \PDO::PARAM_INT);

        $search->joinProductPrice('price'.$defaultCurrencySuffix, Criteria::LEFT_JOIN)
            ->addJoinCondition('price_default_currency', '`price'.$defaultCurrencySuffix.'`.`currency_id` = ?', $defaultCurrency->getId(), null, \PDO::PARAM_INT);

        // A default currency price is converted at rate / default rate, the way the cart
        // converts it: the rate alone is only right while the default rate is 1.
        $factorAsSql = $this->currencyConverter->factorAsSql($currency);

        $priceSelectorAsSQL = 'CASE WHEN ISNULL(`price`.PRICE) OR `price`.FROM_DEFAULT_CURRENCY = 1 THEN `price_default_currency`.PRICE * '.$factorAsSql.' ELSE `price`.PRICE END';
        $promoPriceSelectorAsSQL = 'CASE WHEN ISNULL(`price`.PRICE) OR `price`.FROM_DEFAULT_CURRENCY = 1 THEN `price_default_currency`.PROMO_PRICE * '.$factorAsSql.' ELSE `price`.PROMO_PRICE END';
        $search->withColumn($priceSelectorAsSQL, 'price_PRICE')
            ->withColumn($promoPriceSelectorAsSQL, 'price_PROMO_PRICE')
            ->withColumn('CASE WHEN '.ProductSaleElementsTableMap::COL_PROMO.' = 1 THEN '.$promoPriceSelectorAsSQL.' ELSE '.$priceSelectorAsSQL.' END', 'price_FINAL_PRICE');

        $search->groupById();

        return $search;
    }

    public function parseResults(LoopResult $loopResult): LoopResult
    {
        $taxCountry = $this->taxEngine->getDeliveryCountry();
        $resolvedPrices = $this->resolvedPricesOf($loopResult);
        $saleUnitQuantities = $this->saleUnitQuantitiesOf($loopResult);

        /** @var \Thelia\Model\ProductSaleElements $PSEValue */
        foreach ($loopResult->getResultDataCollection() as $PSEValue) {
            $loopResultRow = new LoopResultRow($PSEValue);

            $isPromo = 1 === $PSEValue->getPromo() ? 1 : 0;

            // What the visitor pays - price of the group, rules, operations, then the
            // discounts of the group and of the customer - writes nothing in the catalog,
            // so it is substituted here, in the virtual columns the price getters read, so
            // that the tax is applied to it exactly the way it is applied to a public price.
            // The promo flag itself is only raised for the row: the model belongs to the
            // catalog.
            $resolvedPrice = $resolvedPrices[(int) $PSEValue->getId()] ?? null;

            if (null !== $resolvedPrice) {
                $PSEValue->setVirtualColumn('price_PRICE', $resolvedPrice->untaxedPrice);
                $PSEValue->setVirtualColumn('price_PROMO_PRICE', $resolvedPrice->untaxedPromoPrice);
                $isPromo = $resolvedPrice->isPromo ? 1 : 0;
            }

            $price = $PSEValue->getPrice('price_PRICE');

            try {
                $taxedPrice = $PSEValue->getTaxedPrice(
                    $taxCountry,
                    'price_PRICE',
                );
            } catch (TaxEngineException) {
                $taxedPrice = null;
            }

            $promoPrice = $PSEValue->getPromoPrice('price_PROMO_PRICE');

            try {
                $taxedPromoPrice = $PSEValue->getTaxedPromoPrice(
                    $taxCountry,
                    'price_PROMO_PRICE',
                );
            } catch (TaxEngineException) {
                $taxedPromoPrice = null;
            }

            $loopResultRow
                ->set('ID', $PSEValue->getId())
                ->set('QUANTITY', $saleUnitQuantities[(int) $PSEValue->getId()] ?? $PSEValue->getQuantity())
                ->set('IS_PROMO', $isPromo)
                ->set('VISIBLE', $PSEValue->getVisible())
                ->set('POSITION', $PSEValue->getPosition())
                ->set('IS_NEW', 1 === $PSEValue->getNewness() ? 1 : 0)
                ->set('IS_DEFAULT', $PSEValue->getIsDefault() ? 1 : 0)
                ->set('WEIGHT', $PSEValue->getWeight())
                ->set('REF', $PSEValue->getRef())
                ->set('EAN_CODE', $PSEValue->getEanCode())
                ->set('MPN', $PSEValue->getMpn())
                ->set('MANUFACTURER_BRAND_ID', $PSEValue->getEffectiveManufacturerBrandId())
                ->set('PRODUCT_ID', $PSEValue->getProductId())
                ->set('PRICE', $price)
                ->set('PRICE_TAX', $taxedPrice - $price)
                ->set('TAXED_PRICE', $taxedPrice)
                ->set('PROMO_PRICE', $promoPrice)
                ->set('PROMO_PRICE_TAX', $taxedPromoPrice - $promoPrice)
                ->set('TAXED_PROMO_PRICE', $taxedPromoPrice);

            $this->addOutputFields($loopResultRow, $PSEValue);
            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }

    /**
     * What the visitor pays for the sale elements of the page, resolved once for the
     * whole page rather than once per row.
     *
     * Empty in the back office: the catalog is what it edits, and what a rule, an
     * operation, a group or a discount gives a customer is deliberately not part of it.
     *
     * @return array<int, ResolvedPrice> keyed by sale element id
     */
    private function resolvedPricesOf(LoopResult $loopResult): array
    {
        if ($this->getBackendContext() || !$this->currency instanceof CurrencyModel) {
            return [];
        }

        $productSaleElementsIds = [];

        /** @var \Thelia\Model\ProductSaleElements $PSEValue */
        foreach ($loopResult->getResultDataCollection() as $PSEValue) {
            $productSaleElementsIds[] = (int) $PSEValue->getId();
        }

        return $this->visitorPriceReader->resolve($productSaleElementsIds, $this->currency);
    }

    /**
     * The stock the sale unit the visitor buys from keeps of the sale elements of the
     * page, in one query; empty, without a query, when no unit is resolved, and in the
     * back office, which edits the national counter.
     *
     * @return array<int, float> keyed by sale element id
     */
    private function saleUnitQuantitiesOf(LoopResult $loopResult): array
    {
        if (null === $this->saleUnitAvailableStock || $this->getBackendContext()) {
            return [];
        }

        $productSaleElementsIds = [];

        /** @var \Thelia\Model\ProductSaleElements $PSEValue */
        foreach ($loopResult->getResultDataCollection() as $PSEValue) {
            $productSaleElementsIds[] = (int) $PSEValue->getId();
        }

        return $this->saleUnitAvailableStock->ownQuantities($productSaleElementsIds);
    }

    /**
     * @return array of available field to search in
     */
    public function getSearchIn(): array
    {
        return [
            'ref',
            'ean_code',
            'mpn',
        ];
    }

    public function doSearch(ModelCriteria $search, $searchTerm, $searchIn, $searchCriteria): void
    {
        $search->_and();

        foreach ($searchIn as $index => $searchInElement) {
            if ($index > 0) {
                $search->_or();
            }

            switch ($searchInElement) {
                case 'ref':
                    $search->filterByRef($searchTerm, $searchCriteria);
                    break;
                case 'ean_code':
                    $search->filterByEanCode($searchTerm, $searchCriteria);
                    break;
                case 'mpn':
                    $search->filterByMpn($searchTerm, $searchCriteria);
                    break;
            }
        }
    }
}
