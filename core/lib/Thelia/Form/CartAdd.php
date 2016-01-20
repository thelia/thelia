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

namespace Thelia\Form;

use Symfony\Component\Validator\Constraints;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Thelia\Form\Exception\StockNotFoundException;
use Thelia\Form\Exception\ProductNotFoundException;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Model\ProductQuery;
use Thelia\Core\Translation\Translator;

/**
 * Class CartAdd
 * @package Thelia\Form
 * @author Manuel Raynaud <manu@raynaud.io>
 */
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
            ->add("product", "text", array(
                "constraints" => array(
                    new Constraints\NotBlank(),
                    new Constraints\Callback(array("methods" => array(
                            array($this, "checkProduct"),
                    ))),
                ),
                "label" => "product",
                "label_attr" => array(
                    "for" => "cart_product",
                ),
            ))
            ->add("product_sale_elements_id", "text", array(
                "constraints" => array(
                    new Constraints\NotBlank(),
                    new Constraints\Callback(array("methods" => array(
                            array($this, "checkStockAvailability"),
                    ))),
                ),
                "required" => true,

            ))
            ->add("quantity", "number", array(
                "constraints" => array(
                    new Constraints\NotBlank(),
                    new Constraints\Callback(array("methods" => array(
                            array($this, "checkStock"),
                    ))),
                    new Constraints\GreaterThanOrEqual(array(
                        "value" => 0,
                    )),
                ),
                "label" => Translator::getInstance()->trans("Quantity"),
                "label_attr" => array(
                    "for" => "quantity",
                ),
            ))
            ->add("append", "integer")
            ->add("newness", "integer")
        ;
    }

    public function checkProduct($value, ExecutionContextInterface $context)
    {
        $product = ProductQuery::create()->findPk($value);

        if (is_null($product) || $product->getVisible() == 0) {
            throw new ProductNotFoundException(sprintf(Translator::getInstance()->trans("this product id does not exists : %d"), $value));
        }
    }

    public function checkStockAvailability($value, ExecutionContextInterface $context)
    {
        if ($value) {
            $data = $context->getRoot()->getData();

            $productSaleElements = ProductSaleElementsQuery::create()
                ->filterById($value)
                ->filterByProductId($data["product"])
                ->count();

            if ($productSaleElements == 0) {
                throw new StockNotFoundException(sprintf(Translator::getInstance()->trans("This product_sale_elements_id does not exists for this product : %d"), $value));
            }
        }
    }

    public function checkStock($value, ExecutionContextInterface $context)
    {
        $data = $context->getRoot()->getData();

        if (null === $data["product_sale_elements_id"]) {
            $context->buildViolation(Translator::getInstance()->trans("Invalid product_sale_elements"));
        } else {
            $productSaleElements = ProductSaleElementsQuery::create()
                ->filterById($data["product_sale_elements_id"])
                ->filterByProductId($data["product"])
                ->findOne();

            $product = $productSaleElements->getProduct();

            if ($productSaleElements->getQuantity() < $value && $product->getVirtual() === 0 && ConfigQuery::checkAvailableStock()) {
                $context->addViolation(Translator::getInstance()->trans("quantity value is not valid"));
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
