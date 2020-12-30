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

use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Validator\Constraints\NotBlank;
use Thelia\Core\Translation\Translator;
use Thelia\Model\ProductQuery;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class ProductCreationForm extends BaseForm
{
    protected function buildForm()
    {
        $this->doBuildForm(false);
    }

    protected function doBuildForm($change_mode)
    {
        $this->formBuilder
            ->add("ref", TextType::class, array(
                "constraints" => array(
                    new NotBlank(),
                    new Callback(array(
                        "methods" => array(array($this, "checkDuplicateRef")),
                    )),
                ),
                "label"       => Translator::getInstance()->trans('Product reference *'),
                "label_attr"  => array("for" => "ref"),
            ))
            ->add("title", TextType::class, array(
                "constraints" => array(new NotBlank()),
                "label" => Translator::getInstance()->trans('Product title'),
                "label_attr" => array("for" => "title"),
            ))
            ->add("default_category", IntegerType::class, array(
                "constraints" => array(new NotBlank()),
                "label"       => Translator::getInstance()->trans("Default product category *"),
                "label_attr"  => array("for" => "default_category_field"),
            ))
            ->add("locale", TextType::class, array(
                "constraints" => array(new NotBlank()),
            ))
            ->add("visible", IntegerType::class, array(
                "label"      => Translator::getInstance()->trans("This product is online"),
                "label_attr" => array("for" => "visible_field"),
            ))
            ->add("virtual", IntegerType::class, array(
                "label"      => Translator::getInstance()->trans("This product does not have a physical presence"),
                "label_attr" => array("for" => "virtual_field"),
            ))
        ;

        if (! $change_mode) {
            $this->formBuilder
                ->add("price", NumberType::class, array(
                    "constraints" => array(new NotBlank()),
                    "label"      => Translator::getInstance()->trans("Product base price excluding taxes *"),
                    "label_attr" => array("for" => "price_without_tax"),
                ))
                ->add("tax_price", NumberType::class, array(
                    "label"      => Translator::getInstance()->trans("Product base price with taxes"),
                    "label_attr" => array("for" => "price_with_tax"),
                ))
                ->add("currency", IntegerType::class, array(
                    "constraints" => array(new NotBlank()),
                    "label"      => Translator::getInstance()->trans("Price currency *"),
                    "label_attr" => array("for" => "currency_field"),
                ))
                ->add("tax_rule", IntegerType::class, array(
                    "constraints" => array(new NotBlank()),
                    "label"      => Translator::getInstance()->trans("Tax rule for this product *"),
                    "label_attr" => array("for" => "tax_rule_field"),
                ))
                ->add("weight", NumberType::class, array(
                    "label"      => Translator::getInstance()->trans("Weight"),
                    "label_attr" => array("for" => "weight_field"),
                ))
                ->add("quantity", NumberType::class, array(
                    "label"      => Translator::getInstance()->trans("Stock"),
                    "label_attr" => array("for" => "quantity_field"),
                    "required"   => false
                ))
                ->add("template_id", IntegerType::class, array(
                    "label"      => Translator::getInstance()->trans("Template"),
                    "label_attr" => array("for" => "template_field"),
                    "required"   => false
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
                )
            );
        }
    }

    public function getName()
    {
        return "thelia_product_creation";
    }
}
