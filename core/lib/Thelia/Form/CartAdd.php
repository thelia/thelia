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
namespace Thelia\Form;

use Propel\Runtime\ActiveQuery\Criteria;
use Symfony\Component\Validator\Constraints;
use Symfony\Component\Validator\ExecutionContextInterface;
use Thelia\Action\Exception\CombinationNotFoundException;
use Thelia\Action\Exception\ProductNotFoundException;
use Thelia\Model\Base\StockQuery;
use Thelia\Model\ProductQuery;

class CartAdd extends BaseForm
{

    /**
     *
     * in this function you add all the fields you need for your Form.
     * Form this you have to call add method on $this->formBuilder attribute :
     *
     * $this->formBuilder->add("name", "text")
     *   ->add("email", "email", array(
     *           "attr" => array(
     *               "class" => "field"
     *           ),
     *           "label" => "email",
     *           "constraints" => array(
     *               new \Symfony\Component\Validator\Constraints\NotBlank()
     *           )
     *       )
     *   )
     *   ->add('age', 'integer');
     *
     * @return null
     */
    protected function buildForm()
    {
        $this->formBuilder
            ->add("product", "hidden", array(
                "constraints" => array(
                    new Constraints\NotBlank(),
                    new Constraints\Callback(array(
                        "methods" => array($this, "checkProduct")
                    ))
                )
            ))
            ->add("stock_id", "hidden", array(
                "constraints" => array(
                    new Constraints\Callback(array(
                        "methods" => array($this, "checkStockAvailability")
                    ))
                )

            ))
            ->add("quantity", "text", array(
                "constraints" => array(
                    new Constraints\NotBlank(),
                    new Constraints\Callback(array(
                        "methods" => array($this, "checkStock")
                    ))
                )
            ))
            ->add("append", "hidden")
            ->add("newness", "hidden")
        ;
    }

    protected function checkProduct($value, ExecutionContextInterface $context)
    {
        $product = ProductQuery::create()->findPk($value);

        if (is_null($product)) {
            throw new ProductNotFoundException(sprintf("this product id does not exists : %d", $value));
        }
    }

    protected function checkStockAvailability($value, ExecutionContextInterface $context)
    {
        if ($value) {
            $data = $context->getRoot()->getData();

            $stock = StockQuery::create()->findPk($value);

            if (is_null($stock)) {
                throw new CombinationNotFoundException(sprintf("This stock_id does not exists for this product : %d", $value));
            }
        }
    }

    protected function checkStock($value, ExecutionContextInterface $context)
    {
        $data = $context->getRoot()->getData();

        $product = ProductQuery::create()->findPk($data["product"]);

        if ($product) {
             if(false === $product->stockIsValid($value, $data["combination"])) {
                $context->addViolation("quantity value is not valid");
             }
        }

    }

    /**
     * @return string the name of you form. This name must be unique
     */
    public function getName()
    {
        return "thelia_cart_add";
    }
}