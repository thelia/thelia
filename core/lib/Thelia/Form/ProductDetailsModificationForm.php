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

use Symfony\Component\Validator\Constraints\GreaterThan;
use Thelia\Core\Translation\Translator;
use Symfony\Component\Validator\Constraints\NotBlank;

class ProductDetailsModificationForm extends BaseForm
{
    use StandardDescriptionFieldsTrait;

    protected function buildForm()
    {
        $this->formBuilder
            ->add("id", "integer", array(
                    "label"       => Translator::getInstance()->trans("Prodcut ID *"),
                    "label_attr"  => array("for" => "product_id_field"),
                    "constraints" => array(new GreaterThan(array('value' => 0)))
            ))
            ->add("price", "number", array(
                "constraints" => array(new NotBlank()),
                "label"      => Translator::getInstance()->trans("Product base price excluding taxes *"),
                "label_attr" => array("for" => "price_field")
            ))
            ->add("price_with_tax", "number", array(
                "label"      => Translator::getInstance()->trans("Product base price including taxes *"),
                "label_attr" => array("for" => "price_with_tax_field")
            ))
            ->add("currency", "integer", array(
                "constraints" => array(new NotBlank()),
                "label"      => Translator::getInstance()->trans("Price currency *"),
                "label_attr" => array("for" => "currency_field")
            ))
            ->add("tax_rule", "integer", array(
                "constraints" => array(new NotBlank()),
                "label"      => Translator::getInstance()->trans("Tax rule for this product *"),
                "label_attr" => array("for" => "tax_rule_field")
            ))
            ->add("weight", "number", array(
                "constraints" => array(new NotBlank()),
                "label"      => Translator::getInstance()->trans("Weight *"),
                "label_attr" => array("for" => "weight_field")
            ))
            ->add("quantity", "number", array(
                "constraints" => array(new NotBlank()),
                "label"      => Translator::getInstance()->trans("Current quantity *"),
                "label_attr" => array("for" => "quantity_field")
            ))
            ->add("sale_price", "number", array(
                "label"      => Translator::getInstance()->trans("Sale price *"),
                "label_attr" => array("for" => "price_with_tax_field")
            ))
            ->add("onsale", "integer", array(
                    "label"      => Translator::getInstance()->trans("This product is on sale"),
                    "label_attr" => array("for" => "onsale_field")
            ))
            ->add("isnew", "integer", array(
                    "label"      => Translator::getInstance()->trans("Advertise this product as new"),
                    "label_attr" => array("for" => "isnew_field")
            ))

        ;
    }

    public function getName()
    {
        return "thelia_product_details_modification";
    }
}
