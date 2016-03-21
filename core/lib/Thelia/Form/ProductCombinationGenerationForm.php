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

use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\GreaterThan;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Thelia\Core\Translation\Translator;

class ProductCombinationGenerationForm extends BaseForm
{
    protected function buildForm()
    {
        $this->formBuilder
        ->add('product_id', 'integer', array(
                'label'       => Translator::getInstance()->trans('Product ID'),
                'label_attr'  => array('for' => 'combination_builder_id_field'),
                'constraints' => array(new GreaterThan(array('value' => 0))),
        ))
        ->add('currency', 'integer', array(
                'label'       => Translator::getInstance()->trans('Price currency *'),
                'label_attr'  => array('for' => 'combination_builder_currency_field'),
                'constraints' => array(new GreaterThan(array('value' => 0))),
        ))
        ->add('reference', 'text', array(
                'label'      => Translator::getInstance()->trans('Reference'),
                'label_attr' => array('for' => 'combination_builder_reference_field'),
        ))
        ->add('price', 'number', array(
                'label'      => Translator::getInstance()->trans('Product price excluding taxes'),
                'label_attr' => array('for' => 'combination_builder_price_field'),
        ))
        ->add('weight', 'number', array(
                'label'      => Translator::getInstance()->trans('Weight'),
                'label_attr' => array('for' => 'combination_builder_weight_field'),
        ))
        ->add('quantity', 'number', array(
                'label'      => Translator::getInstance()->trans('Available quantity'),
                'label_attr' => array('for' => 'combination_builder_quantity_field'),
        ))
        ->add('sale_price', 'number', array(
                'label'      => Translator::getInstance()->trans('Sale price excluding taxes'),
                'label_attr' => array('for' => 'combination_builder_price_with_tax_field'),
        ))
        ->add('onsale', 'integer', array(
                'label'      => Translator::getInstance()->trans('This product is on sale'),
                'label_attr' => array('for' => 'combination_builder_onsale_field'),
        ))
        ->add('isnew', 'integer', array(
                'label'      => Translator::getInstance()->trans('Advertise this product as new'),
                'label_attr' => array('for' => 'combination_builder_isnew_field'),
        ))
        ->add('ean_code', 'text', array(
                'label'      => Translator::getInstance()->trans('EAN Code'),
                'label_attr' => array('for' => 'combination_builder_ean_code_field'),
        ))
        ->add('attribute_av', 'collection', array(
                'type'         => 'text',
                'label'        => Translator::getInstance()->trans('Attribute ID:Attribute AV ID'),
                'label_attr'   => array('for' => 'combination_builder_attribute_av_id'),
                'allow_add'    => true,
                'allow_delete' => true,
                "constraints" => array(
                    new Callback(array(
                        "methods" => array(array($this, "checkAttributeAv")),
                    )),
                )
        ))
        ;
    }

    public function checkAttributeAv($value, ExecutionContextInterface $context)
    {
        if (empty($value)) {
            $context->addViolation(
                Translator::getInstance()->trans(
                    "You must select at least one attribute."
                )
            );
        }
    }

    public function getName()
    {
        return 'thelia_product_combination_generation_form';
    }
}
