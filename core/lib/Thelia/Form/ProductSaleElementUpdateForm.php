<?php

declare(strict_types=1);

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Form;

use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Validator\Constraints\GreaterThan;
use Symfony\Component\Validator\Constraints\NotBlank;
use Thelia\Core\Translation\Translator;
use Thelia\Model\Currency;

class ProductSaleElementUpdateForm extends BaseForm
{
    protected function buildForm(): void
    {
        $this->formBuilder
            ->add('tax_rule', IntegerType::class, [
                'constraints' => [new NotBlank()],
                'label' => Translator::getInstance()->trans('Tax rule for this product *'),
                'label_attr' => ['for' => 'tax_rule_field'],
            ])
            ->add('product_id', IntegerType::class, [
                'label' => Translator::getInstance()->trans('Product ID *'),
                'label_attr' => ['for' => 'product_id_field'],
                'constraints' => [new GreaterThan(['value' => 0])],
            ])
            ->add('default_pse', IntegerType::class, [
                'label' => Translator::getInstance()->trans('Default product sale element'),
                'label_attr' => ['for' => 'default_pse_field'],
            ])
            ->add('currency', IntegerType::class, [
                'constraints' => [new NotBlank()],
                'label' => Translator::getInstance()->trans('Price currency *'),
                'label_attr' => ['for' => 'currency_field'],
            ])
            ->add('use_exchange_rate', IntegerType::class, [
                'label' => Translator::getInstance()->trans('Apply exchange rates on price in %sym', ['%sym' => Currency::getDefaultCurrency()->getSymbol()]),
                'label_attr' => ['for' => 'use_exchange_rate_field'],
            ])

            // -- Collections

            ->add('product_sale_element_id', CollectionType::class, [
                'entry_type' => IntegerType::class,
                'label' => Translator::getInstance()->trans('Product sale element ID *'),
                'label_attr' => ['for' => 'product_sale_element_id_field'],
                'allow_add' => true,
                'allow_delete' => true,
            ])
            ->add('reference', CollectionType::class, [
                'entry_type' => TextType::class,
                'label' => Translator::getInstance()->trans('Reference *'),
                'label_attr' => ['for' => 'reference_field'],
                'allow_add' => true,
                'allow_delete' => true,
            ])
            ->add('price', CollectionType::class, [
                'entry_type' => NumberType::class,
                'label' => Translator::getInstance()->trans('Product price excluding taxes *'),
                'label_attr' => ['for' => 'price_field'],
                'allow_add' => true,
                'allow_delete' => true,
                'entry_options' => [
                    'constraints' => [new NotBlank()],
                ],
            ])
            ->add('price_with_tax', CollectionType::class, [
                'entry_type' => NumberType::class,
                'label' => Translator::getInstance()->trans('Product price including taxes'),
                'label_attr' => ['for' => 'price_with_tax_field'],
                'allow_add' => true,
                'allow_delete' => true,
            ])
            ->add('weight', CollectionType::class, [
                'entry_type' => NumberType::class,
                'label' => Translator::getInstance()->trans('Weight'),
                'label_attr' => ['for' => 'weight_field'],
                'allow_add' => true,
                'allow_delete' => true,
            ])
            ->add('quantity', CollectionType::class, [
                'entry_type' => NumberType::class,
                'label' => Translator::getInstance()->trans('Available quantity *'),
                'label_attr' => ['for' => 'quantity_field'],
                'allow_add' => true,
                'allow_delete' => true,
                'entry_options' => [
                    'constraints' => [new NotBlank()],
                ],
            ])
            ->add(
                'visible',
                CheckboxType::class,
                [
                    'constraints' => [],
                    'required' => false,
                ],
            )
            ->add('sale_price', CollectionType::class, [
                'label' => Translator::getInstance()->trans('Sale price excluding taxes'),
                'label_attr' => ['for' => 'price_with_tax_field'],
                'allow_add' => true,
                'allow_delete' => true,
            ])
            ->add('sale_price_with_tax', CollectionType::class, [
                'entry_type' => NumberType::class,
                'label' => Translator::getInstance()->trans('Sale price including taxes'),
                'label_attr' => ['for' => 'sale_price_with_tax_field'],
                'allow_add' => true,
                'allow_delete' => true,
            ])
            ->add('onsale', CollectionType::class, [
                'entry_type' => IntegerType::class,
                'label' => Translator::getInstance()->trans('This product is on sale'),
                'label_attr' => ['for' => 'onsale_field'],
                'allow_add' => true,
                'allow_delete' => true,
            ])
            ->add('isnew', CollectionType::class, [
                'entry_type' => IntegerType::class,
                'label' => Translator::getInstance()->trans('Advertise this product as new'),
                'label_attr' => ['for' => 'isnew_field'],
                'allow_add' => true,
                'allow_delete' => true,
            ])
            ->add('isdefault', CollectionType::class, [
                'entry_type' => IntegerType::class,
                'label' => Translator::getInstance()->trans('Is it the default product sale element ?'),
                'label_attr' => ['for' => 'isdefault_field'],
                'allow_add' => true,
                'allow_delete' => true,
            ])
            ->add('ean_code', CollectionType::class, [
                'entry_type' => TextType::class,
                'label' => Translator::getInstance()->trans('EAN Code'),
                'label_attr' => ['for' => 'ean_code_field'],
                'allow_add' => true,
                'allow_delete' => true,
            ]);
    }

    public static function getName(): string
    {
        return 'thelia_product_sale_element_update_form';
    }
}
