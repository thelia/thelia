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

use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\GreaterThan;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Thelia\Core\Translation\Translator;

class ProductCombinationGenerationForm extends BaseForm
{
    protected function buildForm(): void
    {
        $this->formBuilder
            ->add('product_id', IntegerType::class, [
                'label' => Translator::getInstance()->trans('Product ID'),
                'label_attr' => ['for' => 'combination_builder_id_field'],
                'constraints' => [new GreaterThan(['value' => 0])],
            ])
            ->add('currency', IntegerType::class, [
                'label' => Translator::getInstance()->trans('Price currency *'),
                'label_attr' => ['for' => 'combination_builder_currency_field'],
                'constraints' => [new GreaterThan(['value' => 0])],
            ])
            ->add('reference', TextType::class, [
                'label' => Translator::getInstance()->trans('Reference'),
                'label_attr' => ['for' => 'combination_builder_reference_field'],
            ])
            ->add('price', NumberType::class, [
                'label' => Translator::getInstance()->trans('Product price excluding taxes'),
                'label_attr' => ['for' => 'combination_builder_price_field'],
            ])
            ->add('weight', NumberType::class, [
                'label' => Translator::getInstance()->trans('Weight'),
                'label_attr' => ['for' => 'combination_builder_weight_field'],
            ])
            ->add('quantity', NumberType::class, [
                'label' => Translator::getInstance()->trans('Available quantity'),
                'label_attr' => ['for' => 'combination_builder_quantity_field'],
            ])
            ->add('sale_price', NumberType::class, [
                'label' => Translator::getInstance()->trans('Sale price excluding taxes'),
                'label_attr' => ['for' => 'combination_builder_price_with_tax_field'],
            ])
            ->add('onsale', IntegerType::class, [
                'label' => Translator::getInstance()->trans('This product is on sale'),
                'label_attr' => ['for' => 'combination_builder_onsale_field'],
            ])
            ->add('isnew', IntegerType::class, [
                'label' => Translator::getInstance()->trans('Advertise this product as new'),
                'label_attr' => ['for' => 'combination_builder_isnew_field'],
            ])
            ->add('ean_code', TextType::class, [
                'label' => Translator::getInstance()->trans('EAN Code'),
                'label_attr' => ['for' => 'combination_builder_ean_code_field'],
            ])
            ->add('attribute_av', CollectionType::class, [
                'entry_type' => TextType::class,
                'label' => Translator::getInstance()->trans('Attribute ID:Attribute AV ID'),
                'label_attr' => ['for' => 'combination_builder_attribute_av_id'],
                'allow_add' => true,
                'allow_delete' => true,
                'constraints' => [
                    new Callback($this->checkAttributeAv(...)),
                ],
            ]);
    }

    public function checkAttributeAv($value, ExecutionContextInterface $context): void
    {
        if (empty($value)) {
            $context->addViolation(
                Translator::getInstance()->trans(
                    'You must select at least one attribute.',
                ),
            );
        }
    }

    public static function getName(): string
    {
        return 'thelia_product_combination_generation_form';
    }
}
