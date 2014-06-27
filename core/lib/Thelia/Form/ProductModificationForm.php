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
use Thelia\Core\Translation\Translator;

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
                    "constraints" => array(new GreaterThan(array('value' => 0)))
            ))
            ->add("template_id", "integer", array(
                    "label"       => Translator::getInstance()->trans("Product template"),
                    "label_attr"  => array("for" => "product_template_field")
            ))
            ->add("brand_id", "integer", [
                'constraints' => [ new NotBlank() ],
                'required'    => true,
                'label'       => Translator::getInstance()->trans('Brand / Supplier'),
                'label_attr'  => [
                    'for' => 'mode',
                ],
                'attr' => [
                    'help' => Translator::getInstance()->trans("Select the product brand, or supplier."),
                ]
            ])
        ;

        // Add standard description fields, excluding title and locale, which a re defined in parent class
        $this->addStandardDescFields(array('title', 'locale'));
    }

    public function getName()
    {
        return "thelia_product_modification";
    }
}
