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

use Symfony\Component\Validator\Constraints\GreaterThan;
use Symfony\Component\Validator\Constraints\NotBlank;
use Thelia\Model\Currency;
use Thelia\Core\Translation\Translator;

class ProductDefaultSaleElementUpdateForm extends ProductSaleElementUpdateForm
{
    protected function buildForm()
    {
        $this->formBuilder
        ->add("product_id", "integer", array(
                "label"       => Translator::getInstance()->trans("Product ID *"),
                "label_attr"  => array("for" => "product_id"),
                "constraints" => array(new GreaterThan(array('value' => 0))),
        ))
        ->add("product_sale_element_id", "integer", array(
                "label"       => Translator::getInstance()->trans("Product sale element ID *"),
                "label_attr"  => array("for" => "product_sale_element_id"),
        ))
        ->add("reference", "text", array(
                "label"      => Translator::getInstance()->trans("Reference *"),
                "label_attr" => array("for" => "reference"),
        ))
        ->add("price", "number", array(
                "constraints" => array(new NotBlank()),
                "label"      => Translator::getInstance()->trans("Product price excluding taxes *"),
                "label_attr" => array("for" => "price"),
        ))
        ->add("price_with_tax", "number", array(
                "label"      => Translator::getInstance()->trans("Product price including taxes"),
                "label_attr" => array("for" => "price_with_tax"),
        ))
        ->add("currency", "integer", array(
                "constraints" => array(new NotBlank()),
                "label"      => Translator::getInstance()->trans("Price currency *"),
                "label_attr" => array("for" => "currency"),
        ))
        ->add("tax_rule", "integer", array(
                "constraints" => array(new NotBlank()),
                "label"      => Translator::getInstance()->trans("Tax rule for this product *"),
                "label_attr" => array("for" => "tax_rule"),
        ))
        ->add("weight", "number", array(
                "label"      => Translator::getInstance()->trans("Weight"),
                "label_attr" => array("for" => "weight"),
        ))
        ->add("quantity", "number", array(
                "constraints" => array(new NotBlank()),
                "label"      => Translator::getInstance()->trans("Available quantity *"),
                "label_attr" => array("for" => "quantity"),
        ))
        ->add("sale_price", "number", array(
                "label"      => Translator::getInstance()->trans("Sale price excluding taxes"),
                "label_attr" => array("for" => "price_with_tax"),
        ))
        ->add("sale_price_with_tax", "number", array(
                "label"      => Translator::getInstance()->trans("Sale price including taxes"),
                "label_attr" => array("for" => "sale_price_with_tax"),
        ))
        ->add("onsale", "integer", array(
                "label"      => Translator::getInstance()->trans("This product is on sale"),
                "label_attr" => array("for" => "onsale"),
        ))
        ->add("isnew", "integer", array(
                "label"      => Translator::getInstance()->trans("Advertise this product as new"),
                "label_attr" => array("for" => "isnew"),
        ))
        ->add("isdefault", "integer", array(
                "label"      => Translator::getInstance()->trans("Is it the default product sale element ?"),
                "label_attr" => array("for" => "isdefault"),
        ))
        ->add("ean_code", "text", array(
                "required"   => false,
                "label"      => Translator::getInstance()->trans("EAN Code"),
                'attr'       => array('placeholder' => 'Product EAN code'),
                "label_attr" => array("for" => "ean_code"),
        ))
        ->add("use_exchange_rate", "integer", array(
                "label"      => Translator::getInstance()->trans("Apply exchange rates on price in %sym", array("%sym" => Currency::getDefaultCurrency()->getSymbol())),
                "label_attr" => array("for" => "use_exchange_rate"),
        ))
        ;
    }

    public function getName()
    {
        return "thelia_product_default_sale_element_update_form";
    }
}
