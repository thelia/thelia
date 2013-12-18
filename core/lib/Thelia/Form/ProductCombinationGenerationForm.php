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
use Thelia\Model\Currency;
use Thelia\Core\Translation\Translator;

class ProductCombinationGenerationForm extends BaseForm
{
    protected function buildForm()
    {
        $this->formBuilder
        ->add('product_id', 'integer', array(
                'label'       => Translator::getInstance()->trans('Product ID'),
                'label_attr'  => array('for' => 'combination_builder_id_field'),
                'constraints' => array(new GreaterThan(array('value' => 0)))
        ))
        ->add('currency', 'integer', array(
                'label'       => Translator::getInstance()->trans('Price currency *'),
                'label_attr'  => array('for' => 'combination_builder_currency_field'),
                'constraints' => array(new GreaterThan(array('value' => 0)))
        ))
        ->add('reference', 'text', array(
                'label'      => Translator::getInstance()->trans('Reference'),
                'label_attr' => array('for' => 'combination_builder_reference_field')
        ))
        ->add('price', 'number', array(
                'label'      => Translator::getInstance()->trans('Product price excluding taxes'),
                'label_attr' => array('for' => 'combination_builder_price_field')
        ))
        ->add('weight', 'number', array(
                'label'      => Translator::getInstance()->trans('Weight'),
                'label_attr' => array('for' => 'combination_builder_weight_field')
        ))
        ->add('quantity', 'number', array(
                'label'      => Translator::getInstance()->trans('Available quantity'),
                'label_attr' => array('for' => 'combination_builder_quantity_field')
        ))
        ->add('sale_price', 'number', array(
                'label'      => Translator::getInstance()->trans('Sale price excluding taxes'),
                'label_attr' => array('for' => 'combination_builder_price_with_tax_field')
        ))
        ->add('onsale', 'integer', array(
                'label'      => Translator::getInstance()->trans('This product is on sale'),
                'label_attr' => array('for' => 'combination_builder_onsale_field')
        ))
        ->add('isnew', 'integer', array(
                'label'      => Translator::getInstance()->trans('Advertise this product as new'),
                'label_attr' => array('for' => 'combination_builder_isnew_field')
        ))
        ->add('ean_code', 'text', array(
                'label'      => Translator::getInstance()->trans('EAN Code'),
                'label_attr' => array('for' => 'combination_builder_ean_code_field')
        ))
        ->add('attribute_av', 'collection', array(
                'type'         => 'text',
                'label'        => Translator::getInstance()->trans('Attribute ID:Attribute AV ID'),
                'label_attr'   => array('for' => 'combination_builder_attribute_av_id'),
                'allow_add'    => true,
                'allow_delete' => true,
        ))
        ;
    }

    public function getName()
    {
        return 'thelia_product_combination_generation_form';
    }
}
