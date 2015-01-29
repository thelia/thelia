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

use Thelia\Core\Template\Element\ArraySearchLoopInterface;
use Thelia\Core\Template\Element\BaseLoop;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\LoopResultRow;
use Thelia\Core\Template\Loop\Argument\Argument;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;

use Thelia\Type;

class Cart extends BaseLoop implements ArraySearchLoopInterface
{
    use \Thelia\Cart\CartTrait;
    /**
     *
     * define all args used in your loop
     *
     * array key is your arg name.
     *
     * example :
     *
     * return array (
     *  "ref",
     *  "id" => "optional",
     *  "stock" => array(
     *          "optional",
     *          "default" => 10
     *          )
     * );
     *
     * @return \Thelia\Core\Template\Loop\Argument\ArgumentCollection
     */
    protected function getArgDefinitions()
    {
        return new ArgumentCollection(
            new Argument(
                'order',
                new Type\TypeCollection(
                    new Type\EnumListType(array('normal', 'reverse'))
                ),
                'normal'
            )
        );
    }

    public function buildArray()
    {
        $cart = $this->getCart($this->getDispatcher(), $this->request);

        if (null === $cart) {
            return array();
        }

        $returnArray = iterator_to_array($cart->getCartItems());

        $orders  = $this->getOrder();

        foreach ($orders as $order) {
            switch ($order) {
                case "reverse":
                    $returnArray = array_reverse($returnArray, false);
                    break;
            }
        }

        return $returnArray;
    }

    public function parseResults(LoopResult $loopResult)
    {
        $taxCountry = $this->container->get('thelia.taxEngine')->getDeliveryCountry();
        $locale = $this->request->getSession()->getLang()->getLocale();
        foreach ($loopResult->getResultDataCollection() as $cartItem) {
            $product = $cartItem->getProduct(null, $locale);
            $productSaleElement = $cartItem->getProductSaleElements();

            $loopResultRow = new LoopResultRow();

            $loopResultRow->set("ITEM_ID", $cartItem->getId());
            $loopResultRow->set("TITLE", $product->getTitle());
            $loopResultRow->set("REF", $product->getRef());
            $loopResultRow->set("QUANTITY", $cartItem->getQuantity());
            $loopResultRow->set("PRODUCT_ID", $product->getId());
            $loopResultRow->set("PRODUCT_URL", $product->getUrl($this->request->getSession()->getLang()->getLocale()))
                ->set("STOCK", $productSaleElement->getQuantity())
                ->set("PRICE", $cartItem->getPrice())
                ->set("PROMO_PRICE", $cartItem->getPromoPrice())
                ->set("TAXED_PRICE", $cartItem->getTaxedPrice($taxCountry))
                ->set("PROMO_TAXED_PRICE", $cartItem->getTaxedPromoPrice($taxCountry))
                ->set("IS_PROMO", $cartItem->getPromo() === 1 ? 1 : 0);
            $loopResultRow->set("PRODUCT_SALE_ELEMENTS_ID", $productSaleElement->getId());
            $loopResultRow->set("PRODUCT_SALE_ELEMENTS_REF", $productSaleElement->getRef());
            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }

    /**
     * Return the event dispatcher,
     *
     * @return \Symfony\Component\EventDispatcher\EventDispatcher
     */
    public function getDispatcher()
    {
        return $this->dispatcher;
    }
}
