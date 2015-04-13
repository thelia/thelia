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

use Symfony\Component\Validator\Constraints\NotBlank;
use Thelia\Core\Translation\Translator;
use Thelia\Model\ProductQuery;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class ProductCreationForm extends BaseForm
{
    protected function buildForm($change_mode = false)
    {
        $this->formBuilder
            ->add("ref", "text", array(
                "constraints" => array(
                    new NotBlank(),
                    new Callback(array(
                        "methods" => array(array($this, "checkDuplicateRef")),
                    )),
                ),
                "label"       => $this->translator->trans('Product reference'),
                "label_attr"  => array("for" => "ref"),
            ))
            ->add("title", "text", array(
                "constraints" => array(new NotBlank()),
                "label" => $this->translator->trans('Product title'),
                "label_attr" => array("for" => "title"),
            ))
            ->add("default_category", "integer", array(
                "constraints" => array(new NotBlank()),
                "label"       => $this->translator->trans("Default product category"),
                "label_attr"  => array(
                    "for" => "default_category_field",
                    "help" => $this->translator->trans(
                        'You can attach this product to more categories in the "Associations" tab.'
                    )
                ),
            ))
            ->add("locale", "text", array(
                "constraints" => array(new NotBlank()),
            ))
            ->add("visible", "checkbox", array(
                "label"      => $this->translator->trans("This product is online"),
                "label_attr" => array("for" => "visible_field"),
            ))
            ->add("virtual", "checkbox", array(
                "label"      => $this->translator->trans("This product does not have a physical presence"),
                "label_attr" => array("for" => "virtual_field"),
            ))
        ;

        if (! $change_mode) {
            $this->formBuilder
                ->add("price", "number", array(
                    "constraints" => array(new NotBlank()),
                    "label"      => $this->translator->trans("Product base price excluding taxes"),
                    "label_attr" => array("for" => "price_without_tax"),
                ))
               ->add("tax_price", "number", array(
                       "label"      => $this->translator->trans("Product base price with taxes"),
                       "label_attr" => array("for" => "price_with_tax"),
                ))
                ->add("currency", "integer", array(
                    "constraints" => array(new NotBlank()),
                    "label"      => $this->translator->trans("Price currency"),
                    "label_attr" => array("for" => "currency_field"),
                ))
                ->add("tax_rule", "integer", array(
                    "constraints" => array(new NotBlank()),
                    "label"      => $this->translator->trans("Tax rule for this product"),
                    "label_attr" => array("for" => "tax_rule_field"),
                ))
                ->add("weight", "number", array(
                    "label"      => $this->translator->trans("Weight"),
                    "label_attr" => array("for" => "weight_field"),
                ))
                ->add("quantity", "number", array(
                    "label"      => Translator::getInstance()->trans("Stock"),
                    "label_attr" => array("for" => "quantity_field"),
                    "required"   => false
                ))
                ->add("template_id", "integer", array(
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
                $this->translator->trans(
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
