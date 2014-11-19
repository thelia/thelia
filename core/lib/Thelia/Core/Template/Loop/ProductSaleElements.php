<?php
/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace Thelia\Core\Template\Loop;

use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Core\Template\Element\BaseLoop;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\LoopResultRow;
use Thelia\Core\Template\Element\PropelSearchLoopInterface;
use Thelia\Core\Template\Loop\Argument\Argument;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Exception\TaxEngineException;
use Thelia\Model\CurrencyQuery;
use Thelia\Model\Map\ProductSaleElementsTableMap;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Type;
use Thelia\Type\TypeCollection;

/**
 *
 * Product Sale Elements loop
 *
 * @todo : manage attribute_availability ?
 *
 * Class ProductSaleElements
 * @package Thelia\Core\Template\Loop
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 */
class ProductSaleElements extends BaseLoop implements PropelSearchLoopInterface
{
    protected $timestampable = true;

    /**
     * @return ArgumentCollection
     */
    protected function getArgDefinitions()
    {
        return new ArgumentCollection(
            Argument::createIntListTypeArgument('id'),
            Argument::createIntTypeArgument('currency'),
            Argument::createIntTypeArgument('product'),
            Argument::createBooleanTypeArgument('promo'),
            Argument::createBooleanTypeArgument('new'),
            Argument::createBooleanTypeArgument('default'),
            new Argument(
                'attribute_availability',
                new TypeCollection(
                    new Type\IntToCombinedIntsListType()
                )
            ),
            new Argument(
                'order',
                new TypeCollection(
                    new Type\EnumListType(array('quantity', 'quantity_reverse', 'min_price', 'max_price', 'promo', 'new', 'random'))
                ),
                'random'
            )
        );
    }

    public function buildModelCriteria()
    {
        $search = ProductSaleElementsQuery::create();

        $id = $this->getId();

        if (! is_null($id)) {
            $search->filterById($id, Criteria::IN);
        } else {
            $product = $this->getProduct();

            if (! is_null($product)) {
                $search->filterByProductId($product, Criteria::EQUAL);
            } else {
                throw new \InvalidArgumentException("Either 'id' or 'product' argument should be present");
            }
        }

        $promo = $this->getPromo();

        if (null !== $promo) {
            $search->filterByPromo($promo);
        }

        $new = $this->getNew();

        if (null !== $new) {
            $search->filterByPromo($new);
        }

        $default = $this->getDefault();

        if (null !== $default) {
            $search->filterByIsDefault($default);
        }

        $orders  = $this->getOrder();

        foreach ($orders as $order) {
            switch ($order) {
                case "quantity":
                    $search->orderByQuantity(Criteria::ASC);
                    break;
                case "quantity_reverse":
                    $search->orderByQuantity(Criteria::DESC);
                    break;
                case "min_price":
                    $search->addAscendingOrderByColumn('price_FINAL_PRICE', Criteria::ASC);
                    break;
                case "max_price":
                    $search->addDescendingOrderByColumn('price_FINAL_PRICE');
                    break;
                case "promo":
                    $search->orderByPromo(Criteria::DESC);
                    break;
                case "new":
                    $search->orderByNewness(Criteria::DESC);
                    break;
                case "random":
                    $search->clearOrderByColumns();
                    $search->addAscendingOrderByColumn('RAND()');
                    break(2);
            }
        }

        $currencyId = $this->getCurrency();
        if (null !== $currencyId) {
            $currency = CurrencyQuery::create()->findPk($currencyId);
            if (null === $currency) {
                throw new \InvalidArgumentException('Cannot found currency id: `' . $currency . '` in product_sale_elements loop');
            }
        } else {
            $currency = $this->request->getSession()->getCurrency();
        }

        $defaultCurrency = CurrencyQuery::create()->findOneByByDefault(1);
        $defaultCurrencySuffix = '_default_currency';

        $search->joinProductPrice('price', Criteria::LEFT_JOIN)
            ->addJoinCondition('price', '`price`.`currency_id` = ?', $currency->getId(), null, \PDO::PARAM_INT);

        $search->joinProductPrice('price' . $defaultCurrencySuffix, Criteria::LEFT_JOIN)
            ->addJoinCondition('price_default_currency', '`price' . $defaultCurrencySuffix . '`.`currency_id` = ?', $defaultCurrency->getId(), null, \PDO::PARAM_INT);

        /**
         * rate value is checked as a float in overloaded getRate method.
         */
        $priceSelectorAsSQL = 'ROUND(CASE WHEN ISNULL(`price`.PRICE) OR `price`.FROM_DEFAULT_CURRENCY = 1 THEN `price_default_currency`.PRICE * ' . $currency->getRate() . ' ELSE `price`.PRICE END, 2)';
        $promoPriceSelectorAsSQL = 'ROUND(CASE WHEN ISNULL(`price`.PRICE) OR `price`.FROM_DEFAULT_CURRENCY = 1 THEN `price_default_currency`.PROMO_PRICE  * ' . $currency->getRate() . ' ELSE `price`.PROMO_PRICE END, 2)';
        $search->withColumn($priceSelectorAsSQL, 'price_PRICE')
            ->withColumn($promoPriceSelectorAsSQL, 'price_PROMO_PRICE')
            ->withColumn('CASE WHEN ' . ProductSaleElementsTableMap::PROMO . ' = 1 THEN ' . $promoPriceSelectorAsSQL . ' ELSE ' . $priceSelectorAsSQL . ' END', 'price_FINAL_PRICE');

        $search->groupById();

        return $search;
    }

    public function parseResults(LoopResult $loopResult)
    {
        $taxCountry = $this->container->get('thelia.taxEngine')->getDeliveryCountry();
        /** @var \Thelia\Core\Security\SecurityContext $securityContext */
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
            } catch (TaxEngineException $e) {
                $taxedPrice = null;
            }

            $promoPrice = $PSEValue->getPromoPrice('price_PROMO_PRICE', $discount);
            try {
                $taxedPromoPrice = $PSEValue->getTaxedPromoPrice(
                    $taxCountry,
                    'price_PROMO_PRICE',
                    $discount
                );
            } catch (TaxEngineException $e) {
                $taxedPromoPrice = null;
            }

            $loopResultRow
                ->set("ID", $PSEValue->getId())
                ->set("QUANTITY", $PSEValue->getQuantity())
                ->set("IS_PROMO", $PSEValue->getPromo() === 1 ? 1 : 0)
                ->set("IS_NEW", $PSEValue->getNewness() === 1 ? 1 : 0)
                ->set("IS_DEFAULT", $PSEValue->getIsDefault() === 1 ? 1 : 0)
                ->set("WEIGHT", $PSEValue->getWeight())
                ->set("REF", $PSEValue->getRef())
                ->set("EAN_CODE", $PSEValue->getEanCode())
                ->set("PRODUCT_ID", $PSEValue->getProductId())
                ->set("PRICE", $price)
                ->set("PRICE_TAX", $taxedPrice - $price)
                ->set("TAXED_PRICE", $taxedPrice)
                ->set("PROMO_PRICE", $promoPrice)
                ->set("PROMO_PRICE_TAX", $taxedPromoPrice - $promoPrice)
                ->set("TAXED_PROMO_PRICE", $taxedPromoPrice);

            $this->addOutputFields($loopResultRow, $PSEValue);
            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }
}
