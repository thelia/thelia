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
use Thelia\Core\Security\SecurityContext;
use Thelia\Core\Template\Element\BaseLoop;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\LoopResultRow;
use Thelia\Core\Template\Element\PropelSearchLoopInterface;
use Thelia\Core\Template\Element\SearchLoopInterface;
use Thelia\Core\Template\Loop\Argument\Argument;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Exception\TaxEngineException;
use Thelia\Model\Currency as CurrencyModel;
use Thelia\Model\CurrencyQuery;
use Thelia\Model\Map\ProductSaleElementsTableMap;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\TaxEngine\TaxEngine;
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

    public function __construct(
        protected readonly TaxEngine $taxEngine,
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
                    new IntToCombinedIntsListType()
                )
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
                        ]
                    )
                ),
                'random'
            )
        );
    }

    public function buildModelCriteria()
    {
        $search = ProductSaleElementsQuery::create();

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
            $search->useProductQuery()
                ->filterByVisible($visible)
            ->endUse();
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
            $currency = $this->getCurrentRequest()->getSession()->getCurrency();
        }

        $defaultCurrency = CurrencyModel::getDefaultCurrency();
        $defaultCurrencySuffix = '_default_currency';

        $search->joinProductPrice('price', Criteria::LEFT_JOIN)
            ->addJoinCondition('price', '`price`.`currency_id` = ?', $currency->getId(), null, \PDO::PARAM_INT);

        $search->joinProductPrice('price'.$defaultCurrencySuffix, Criteria::LEFT_JOIN)
            ->addJoinCondition('price_default_currency', '`price'.$defaultCurrencySuffix.'`.`currency_id` = ?', $defaultCurrency->getId(), null, \PDO::PARAM_INT);

        /**
         * rate value is checked as a float in overloaded getRate method.
         */
        $priceSelectorAsSQL = 'CASE WHEN ISNULL(`price`.PRICE) OR `price`.FROM_DEFAULT_CURRENCY = 1 THEN `price_default_currency`.PRICE * '.$currency->getRate().' ELSE `price`.PRICE END';
        $promoPriceSelectorAsSQL = 'CASE WHEN ISNULL(`price`.PRICE) OR `price`.FROM_DEFAULT_CURRENCY = 1 THEN `price_default_currency`.PROMO_PRICE  * '.$currency->getRate().' ELSE `price`.PROMO_PRICE END';
        $search->withColumn($priceSelectorAsSQL, 'price_PRICE')
            ->withColumn($promoPriceSelectorAsSQL, 'price_PROMO_PRICE')
            ->withColumn('CASE WHEN '.ProductSaleElementsTableMap::COL_PROMO.' = 1 THEN '.$promoPriceSelectorAsSQL.' ELSE '.$priceSelectorAsSQL.' END', 'price_FINAL_PRICE');

        $search->groupById();

        return $search;
    }

    public function parseResults(LoopResult $loopResult): LoopResult
    {
        $taxCountry = $this->taxEngine->getDeliveryCountry();
        /** @var SecurityContext $securityContext */
        $securityContext = $this->container->get('thelia.securityContext');
        $discount = 0;

        if ($securityContext->hasCustomerUser() && $securityContext->getCustomerUser()->getDiscount() > 0) {
            $discount = $securityContext->getCustomerUser()->getDiscount();
        }

        /** @var \Thelia\Model\ProductSaleElements $PSEValue */
        foreach ($loopResult->getResultDataCollection() as $PSEValue) {
            $loopResultRow = new LoopResultRow($PSEValue);

            $price = $PSEValue->getPrice('price_PRICE', $discount);
            try {
                $taxedPrice = $PSEValue->getTaxedPrice(
                    $taxCountry,
                    'price_PRICE',
                    $discount
                );
            } catch (TaxEngineException) {
                $taxedPrice = null;
            }

            $promoPrice = $PSEValue->getPromoPrice('price_PROMO_PRICE', $discount);
            try {
                $taxedPromoPrice = $PSEValue->getTaxedPromoPrice(
                    $taxCountry,
                    'price_PROMO_PRICE',
                    $discount
                );
            } catch (TaxEngineException) {
                $taxedPromoPrice = null;
            }

            $loopResultRow
                ->set('ID', $PSEValue->getId())
                ->set('QUANTITY', $PSEValue->getQuantity())
                ->set('IS_PROMO', $PSEValue->getPromo() === 1 ? 1 : 0)
                ->set('IS_NEW', $PSEValue->getNewness() === 1 ? 1 : 0)
                ->set('IS_DEFAULT', $PSEValue->getIsDefault() ? 1 : 0)
                ->set('WEIGHT', $PSEValue->getWeight())
                ->set('REF', $PSEValue->getRef())
                ->set('EAN_CODE', $PSEValue->getEanCode())
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
     * @return array of available field to search in
     */
    public function getSearchIn(): array
    {
        return [
            'ref',
            'ean_code',
        ];
    }

    /**
     * @param ProductSaleElementsQuery $search
     */
    public function doSearch(&$search, $searchTerm, $searchIn, $searchCriteria): void
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
            }
        }
    }
}
