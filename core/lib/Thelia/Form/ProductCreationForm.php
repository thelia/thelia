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

use Symfony\Component\Validator\Constraints\NotBlank;
use Thelia\Core\Translation\Translator;
use Thelia\Model\ProductQuery;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\ExecutionContextInterface;

class ProductCreationForm extends BaseForm
{
    protected function buildForm($change_mode = false)
    {
        $ref_constraints = array(new NotBlank());

        if (! $change_mode) {
            $ref_constraints[] = new Callback(array(
                "methods" => array(array($this, "checkDuplicateRef"))
            ));
        }

        $this->formBuilder
            ->add("ref", "text", array(
                "constraints" => $ref_constraints,
                "label"       => Translator::getInstance()->trans('Product reference *'),
                "label_attr"  => array("for" => "ref")
            ))
            ->add("title", "text", array(
                "constraints" => array(new NotBlank()),
                "label" => Translator::getInstance()->trans('Product title *'),
                "label_attr" => array("for" => "title")
            ))
            ->add("default_category", "integer", array(
                "constraints" => array(new NotBlank()),
                "label"       => Translator::getInstance()->trans("Default product category *"),
                "label_attr"  => array("for" => "default_category_field")
            ))
            ->add("locale", "text", array(
                "constraints" => array(new NotBlank())
            ))
            ->add("visible", "integer", array(
                "label"      => Translator::getInstance()->trans("This product is online"),
                "label_attr" => array("for" => "visible_field")
            ))
            ;

       if (! $change_mode) {
           $this->formBuilder
                ->add("price", "number", array(
                    "constraints" => array(new NotBlank()),
                    "label"      => Translator::getInstance()->trans("Product base price excluding taxes *"),
                    "label_attr" => array("for" => "price_field")
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
                    "label"      => Translator::getInstance()->trans("Weight *"),
                    "label_attr" => array("for" => "weight_field")
                ))
            ;
        }
    }

    public function checkDuplicateRef($value, ExecutionContextInterface $context)
    {
        $count = ProductQuery::create()->filterByRef($value)->count();

        if ($count > 0) {
            $context->addViolation(
                    Translator::getInstance()->trans(
                            "A product with reference %ref already exists. Please choose another reference.",
                            array('%ref' => $value)
            ));
        }
    }

    public function getName()
    {
        return "thelia_product_creation";
    }
}
