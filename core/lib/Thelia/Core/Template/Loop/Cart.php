<?php
/**
 * Created by JetBrains PhpStorm.
 * User: manu
 * Date: 08/08/13
 * Time: 12:44
 * To change this template use File | Settings | File Templates.
 */

namespace Thelia\Core\Template\Loop;

use Thelia\Core\Template\Element\BaseLoop;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\LoopResultRow;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;

class Cart extends BaseLoop
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

        );
    }

    /**
     *
     * this function have to be implement in your own loop class.
     *
     * All your parameters are defined in defineArgs() and can be accessible like a class property.
     *
     * example :
     *
     * public function defineArgs()
     * {
     *  return array (
     *      "ref",
     *      "id" => "optional",
     *      "stock" => array(
     *          "optional",
     *          "default" => 10
     *          )
     *  );
     * }
     *
     * you can retrieve ref value using $this->ref
     *
     * @param $pagination
     *
     * @return mixed
     */
    public function exec(&$pagination)
    {
        $result = new LoopResult();
        $cart = $this->getCart($this->request);

        if ($cart === null) {
            return $result;
        }

        $cartItems = $cart->getCartItems();

        foreach ($cartItems as $cartItem) {
            $product = $cartItem->getProduct();
            //$product->setLocale($this->request->getSession()->getLocale());

            $loopResultRow = new LoopResultRow();

            $loopResultRow->set("ITEM_ID", $cartItem->getId());
            $loopResultRow->set("TITLE", $product->getTitle());
            $loopResultRow->set("REF", $product->getRef());
            $loopResultRow->set("QUANTITY", $cartItem->getQuantity());
            $loopResultRow->set("PRICE", $cartItem->getPrice());
            $loopResultRow->set("PRODUCT_ID", $product->getId());
            $result->addRow($loopResultRow);
        }

        return $result;
    }

}
