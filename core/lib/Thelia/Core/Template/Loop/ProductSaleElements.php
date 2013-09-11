<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : info@thelia.net                                                      */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      This program is free software; you can redistribute it and/or modify         */
/*      it under the terms of the GNU General Public License as published by         */
/*      the Free Software Foundation; either version 3 of the License                */
/*                                                                                   */
/*      This program is distributed in the hope that it will be useful,              */
/*      but WITHOUT ANY WARRANTY; without even the implied warranty of               */
/*      MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the                */
/*      GNU General Public License for more details.                                 */
/*                                                                                   */
/*      You should have received a copy of the GNU General Public License            */
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.         */
/*                                                                                   */
/*************************************************************************************/

namespace Thelia\Core\Template\Loop;

use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Core\Template\Element\BaseLoop;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\LoopResultRow;

use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Core\Template\Loop\Argument\Argument;

use Thelia\Model\Base\ProductSaleElementsQuery;
use Thelia\Model\CountryQuery;
use Thelia\Model\CurrencyQuery;
use Thelia\Type\TypeCollection;
use Thelia\Type;

/**
 *
 * Product Sale Elements loop
 *
 * @todo : manage currency and attribute_availability
 *
 * Class ProductSaleElements
 * @package Thelia\Core\Template\Loop
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 */
class ProductSaleElements extends BaseLoop
{
    public $timestampable = true;

    /**
     * @return ArgumentCollection
     */
    protected function getArgDefinitions()
    {
        return new ArgumentCollection(
            Argument::createIntTypeArgument('currency'),
            Argument::createIntTypeArgument('product', null, true),
            new Argument(
                'attribute_availability',
                new TypeCollection(
                    new Type\IntToCombinedIntsListType()
                )
            ),
            new Argument(
                'order',
                new TypeCollection(
                    new Type\EnumListType(array('alpha', 'alpha_reverse', 'attribute', 'attribute_reverse'))
                ),
                'attribute'
            )
        );
    }

    /**
     * @param $pagination
     *
     * @return \Thelia\Core\Template\Element\LoopResult
     */
    public function exec(&$pagination)
    {
        $search = ProductSaleElementsQuery::create();

        $product = $this->getProduct();

        $search->filterByProductId($product, Criteria::EQUAL);

        $orders  = $this->getOrder();

        foreach ($orders as $order) {
            switch ($order) {
                case "min_price":
                    $search->addAscendingOrderByColumn('real_lowest_price', Criteria::ASC);
                    break;
                case "max_price":
                    $search->addDescendingOrderByColumn('real_lowest_price');
                    break;
                case "promo":
                    $search->addDescendingOrderByColumn('main_product_is_promo');
                    break;
                case "new":
                    $search->addDescendingOrderByColumn('main_product_is_new');
                    break;
                case "random":
                    $search->clearOrderByColumns();
                    $search->addAscendingOrderByColumn('RAND()');
                    break(2);
            }
        }

        $currencyId = $this->getCurrency();
        if (null !== $currency) {
            $currency = CurrencyQuery::create()->findOneById($currencyId);
            if (null === $currency) {
                throw new \InvalidArgumentException('Cannot found currency id: `' . $currency . '` in product_sale_elements loop');
            }
        } else {
            $currency = $this->request->getSession()->getCurrency();
        }

        $defaultCurrency = CurrencyQuery::create()->findOneByByDefault(1);
        $defaultCurrencySuffix = '_default_currency';

        $search->joinProductPrice('price', Criteria::INNER_JOIN);
            //->addJoinCondition('price', '');

        $search->withColumn('`price`.CURRENCY_ID', 'price_CURRENCY_ID')
            ->withColumn('`price`.PRICE', 'price_PRICE')
            ->withColumn('`price`.PROMO_PRICE', 'price_PROMO_PRICE');

        $PSEValues = $this->search($search, $pagination);

        $loopResult = new LoopResult($PSEValues);

        foreach ($PSEValues as $PSEValue) {
            $loopResultRow = new LoopResultRow($loopResult, $PSEValue, $this->versionable, $this->timestampable, $this->countable);

            $price = $PSEValue->getPrice();
            $taxedPrice = $PSEValue->getTaxedPrice(
                CountryQuery::create()->findOneById(64) // @TODO : make it magic
            );
            $promoPrice = $PSEValue->getPromoPrice();
            $taxedPromoPrice = $PSEValue->getTaxedPromoPrice(
                CountryQuery::create()->findOneById(64) // @TODO : make it magic
            );

            $loopResultRow->set("ID", $PSEValue->getId())
                ->set("QUANTITY", $PSEValue->getQuantity())
                ->set("IS_PROMO", $PSEValue->getPromo() === 1 ? 1 : 0)
                ->set("IS_NEW", $PSEValue->getNewness() === 1 ? 1 : 0)
                ->set("WEIGHT", $PSEValue->getWeight())

                ->set("CURRENCY", $PSEValue->getVirtualColumn('price_CURRENCY_ID'))
                ->set("PRICE", $price)
                ->set("PRICE_TAX", $taxedPrice - $price)
                ->set("TAXED_PRICE", $taxedPrice)
                ->set("PROMO_PRICE", $promoPrice)
                ->set("PROMO_PRICE_TAX", $taxedPromoPrice - $promoPrice)
                ->set("TAXED_PROMO_PRICE", $taxedPromoPrice);

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }
}
