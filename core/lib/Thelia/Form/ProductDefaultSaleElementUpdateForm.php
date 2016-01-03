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
                "label_attr"  => array("for" => "product_id_field"),
                "constraints" => array(new GreaterThan(array('value' => 0))),
        ))
        ->add("product_sale_element_id", "integer", array(
                "label"       => Translator::getInstance()->trans("Product sale element ID *"),
                "label_attr"  => array("for" => "product_sale_element_id_field"),
        ))
        ->add("reference", "text", array(
                "label"      => Translator::getInstance()->trans("Reference *"),
                "label_attr" => array("for" => "reference_field"),
        ))
        ->add("price", "number", array(
                "constraints" => array(new NotBlank()),
                "label"      => Translator::getInstance()->trans("Product price excluding taxes *"),
                "label_attr" => array("for" => "price_field"),
        ))
        ->add("price_with_tax", "number", array(
                "label"      => Translator::getInstance()->trans("Product price including taxes"),
                "label_attr" => array("for" => "price_with_tax_field"),
        ))
        ->add("currency", "integer", array(
                "constraints" => array(new NotBlank()),
                "label"      => Translator::getInstance()->trans("Price currency *"),
                "label_attr" => array("for" => "currency_field"),
        ))
        ->add("tax_rule", "integer", array(
                "constraints" => array(new NotBlank()),
                "label"      => Translator::getInstance()->trans("Tax rule for this product *"),
                "label_attr" => array("for" => "tax_rule_field"),
        ))
        ->add("weight", "number", array(
                "label"      => Translator::getInstance()->trans("Weight"),
                "label_attr" => array("for" => "weight_field"),
        ))
        ->add("quantity", "number", array(
                "constraints" => array(new NotBlank()),
                "label"      => Translator::getInstance()->trans("Available quantity *"),
                "label_attr" => array("for" => "quantity_field"),
        ))
        ->add("sale_price", "number", array(
                "label"      => Translator::getInstance()->trans("Sale price excluding taxes"),
                "label_attr" => array("for" => "price_with_tax_field"),
        ))
        ->add("sale_price_with_tax", "number", array(
                "label"      => Translator::getInstance()->trans("Sale price including taxes"),
                "label_attr" => array("for" => "sale_price_with_tax_field"),
        ))
        ->add("onsale", "integer", array(
                "label"      => Translator::getInstance()->trans("This product is on sale"),
                "label_attr" => array("for" => "onsale_field"),
        ))
        ->add("isnew", "integer", array(
                "label"      => Translator::getInstance()->trans("Advertise this product as new"),
                "label_attr" => array("for" => "isnew_field"),
        ))
        ->add("isdefault", "integer", array(
                "label"      => Translator::getInstance()->trans("Is it the default product sale element ?"),
                "label_attr" => array("for" => "isdefault_field"),
        ))
        ->add("ean_code", "text", array(
                "label"      => Translator::getInstance()->trans("EAN Code"),
                "label_attr" => array("for" => "ean_code_field"),
        ))
        ->add("use_exchange_rate", "integer", array(
                "label"      => Translator::getInstance()->trans("Apply exchange rates on price in %sym", array("%sym" => Currency::getDefaultCurrency()->getSymbol())),
                "label_attr" => array("for" => "use_exchange_rate_field"),
        ))
        ;
    }

    public function getName()
    {
        return "thelia_product_default_sale_element_update_form";
    }
}
