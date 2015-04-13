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

class ProductSaleElementUpdateForm extends BaseForm
{
    protected function buildForm()
    {
        $this->formBuilder
            ->add("tax_rule", "integer", array(
                    "constraints" => array(new NotBlank()),
                    "label"      => $this->translator->trans("Tax rule for this product"),
                    "label_attr" => array("for" => "tax_rule_field"),
            ))
            ->add("product_id", "integer", array(
                    "label"       => $this->translator->trans("Product ID"),
                    "label_attr"  => array("for" => "product_id_field"),
                    "constraints" => array(new GreaterThan(array('value' => 0))),
            ))
            ->add("default_pse", "integer", array(
                    "label"       => $this->translator->trans("Default product sale element"),
                    "label_attr"  => array("for" => "default_pse_field"),
            ))
            ->add("currency", "integer", array(
                    "constraints" => array(new NotBlank()),
                    "label"      => $this->translator->trans("Price currency"),
                    "label_attr" => array("for" => "currency_field"),
            ))
            ->add("use_exchange_rate", "integer", array(
                    "label"      => $this->translator->trans("Apply exchange rates on price in %sym", array("%sym" => Currency::getDefaultCurrency()->getSymbol())),
                    "label_attr" => array("for" => "use_exchange_rate_field"),
            ))

            // -- Collections

            ->add('product_sale_element_id', 'collection', array(
                'type'         => 'integer',
                'label'        => $this->translator->trans('Product sale element ID'),
                'label_attr'   => array('for' => 'product_sale_element_id_field'),
                'allow_add'    => true,
                'allow_delete' => true,
            ))
            ->add('reference', 'collection', array(
                'type'         => 'text',
                'label'        => $this->translator->trans('Reference'),
                'label_attr'   => array('for' => 'reference_field'),
                'allow_add'    => true,
                'allow_delete' => true,
            ))
            ->add('price', 'collection', array(
                'type'         => 'number',
                'label'        => $this->translator->trans('Product price excluding taxes'),
                'attr'         => array('placeholder' => $this->translator->trans('Price excl. taxes')),
                'label_attr'   => array('for' => 'price_field'),
                'allow_add'    => true,
                'allow_delete' => true,
                'options'      => array(
                    'constraints' => array(new NotBlank()),
                ),
            ))
            ->add('price_with_tax', 'collection', array(
                'type'         => 'number',
                'label'        => $this->translator->trans('Product price including taxes'),
                'attr'         => array('placeholder' => $this->translator->trans('Price excl. taxes')),
                'label_attr'   => array('for' => 'price_with_tax_field'),
                'allow_add'    => true,
                'allow_delete' => true,
            ))
            ->add('weight', 'collection', array(
                'type'         => 'number',
                'label'        => $this->translator->trans('Weight'),
                'label_attr'   => array('for' => 'weight_field'),
                'allow_add'    => true,
                'allow_delete' => true,
            ))
            ->add('quantity', 'collection', array(
                'type'         => 'number',
                'label'        => $this->translator->trans('Available quantity'),
                'label_attr'   => array('for' => 'quantity_field'),
                'allow_add'    => true,
                'allow_delete' => true,
                'options'      => array(
                        'constraints' => array(new NotBlank()),
                ),
            ))
            ->add('sale_price', 'collection', array(
                'type'         => 'number',
                'label'        => $this->translator->trans('Sale price excluding taxes'),
                'label_attr'   => array('for' => 'price_with_tax_field'),
                'allow_add'    => true,
                'allow_delete' => true,
            ))
            ->add('sale_price_with_tax', 'collection', array(
                'type'         => 'number',
                'label'        => $this->translator->trans('Sale price including taxes'),
                'label_attr'   => array('for' => 'sale_price_with_tax_field'),
                'allow_add'    => true,
                'allow_delete' => true,
            ))
            ->add('onsale', 'collection', array(
                'type'         => 'integer',
                'label'        => $this->translator->trans('This product is on sale'),
                'label_attr'   => array('for' => 'onsale_field'),
                'allow_add'    => true,
                'allow_delete' => true,
            ))
            ->add('isnew', 'collection', array(
                 'type'         => 'integer',
                'label'        => $this->translator->trans('Advertise this product as new'),
                'label_attr'   => array('for' => 'isnew_field'),
                'allow_add'    => true,
                'allow_delete' => true,
            ))
            ->add('isdefault', 'collection', array(
                'type'         => 'integer',
                'label'        => $this->translator->trans('Is it the default product sale element ?'),
                'label_attr'   => array('for' => 'isdefault_field'),
                'allow_add'    => true,
                'allow_delete' => true,
            ))
            ->add('ean_code', 'collection', array(
                'type'         => 'text',
                'required'     => false,
                'label'        => $this->translator->trans('EAN Code'),
                'attr'         => array('placeholder' => 'Product EAN code'),
                'label_attr'   => array('for' => 'ean_code_field'),
                'allow_add'    => true,
                'allow_delete' => true,
            ))
        ;
    }

    public function getName()
    {
        return "thelia_product_sale_element_update_form";
    }
}
