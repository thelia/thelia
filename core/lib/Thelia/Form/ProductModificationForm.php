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

use Propel\Runtime\ActiveQuery\Criteria;
use Symfony\Component\Validator\Constraints\GreaterThan;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Thelia\Core\Translation\Translator;
use Thelia\Model\ProductQuery;

class ProductModificationForm extends ProductCreationForm
{
    use StandardDescriptionFieldsTrait;

    protected function buildForm()
    {
        parent::buildForm(true);

        $this->formBuilder
            ->add("id", "integer", array(
                    "label"       => Translator::getInstance()->trans("Prodcut ID *"),
                    "label_attr"  => array("for" => "product_id_field"),
                    "constraints" => array(new GreaterThan(array('value' => 0))),
            ))
            ->add("template_id", "integer", array(
                    "label"       => Translator::getInstance()->trans("Product template"),
                    "label_attr"  => array("for" => "product_template_field"),
            ))
            ->add("brand_id", "integer", [
                'constraints' => [ new NotBlank() ],
                'required'    => true,
                'label'       => Translator::getInstance()->trans('Brand / Supplier'),
                'label_attr'  => [
                    'for' => 'mode',
                    'help' => Translator::getInstance()->trans("Select the product brand, or supplier."),
                ],
            ])
            ->add("virtual_document_id", "integer", array(
                "label"      => Translator::getInstance()->trans("Virtual document"),
                "label_attr" => array("for" => "virtual_document_id_field"),
            ))
        ;

        // Add standard description fields, excluding title and locale, which a re defined in parent class
        $this->addStandardDescFields(array('title', 'locale'));
    }

    public function checkDuplicateRef($value, ExecutionContextInterface $context)
    {
        $data = $context->getRoot()->getData();

        $count = ProductQuery::create()
                ->filterById($data['id'], Criteria::NOT_EQUAL)
                ->filterByRef($value)->count();

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
        return "thelia_product_modification";
    }
}
