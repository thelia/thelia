<?php

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

use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Thelia\Core\Translation\Translator;
use Thelia\Model\ProductQuery;

class ProductCreationForm extends BaseForm
{
    protected function buildForm()
    {
        $this->doBuildForm(false);
    }

    protected function doBuildForm($change_mode)
    {
        $this->formBuilder
            ->add('ref', TextType::class, [
                'constraints' => [
                    new NotBlank(),
                    new Callback([$this, 'checkDuplicateRef']),
                ],
                'label' => Translator::getInstance()->trans('Product reference *'),
                'label_attr' => ['for' => 'ref'],
            ])
            ->add('title', TextType::class, [
                'constraints' => [new NotBlank()],
                'label' => Translator::getInstance()->trans('Product title'),
                'label_attr' => ['for' => 'title'],
            ])
            ->add('default_category', IntegerType::class, [
                'constraints' => [new NotBlank()],
                'label' => Translator::getInstance()->trans('Default product category *'),
                'label_attr' => ['for' => 'default_category_field'],
            ])
            ->add('locale', TextType::class, [
                'constraints' => [new NotBlank()],
            ])
            ->add('visible', IntegerType::class, [
                'label' => Translator::getInstance()->trans('This product is online'),
                'label_attr' => ['for' => 'visible_field'],
            ])
            ->add('virtual', IntegerType::class, [
                'label' => Translator::getInstance()->trans('This product does not have a physical presence'),
                'label_attr' => ['for' => 'virtual_field'],
            ])
        ;

        if (!$change_mode) {
            $this->formBuilder
                ->add('price', NumberType::class, [
                    'constraints' => [new NotBlank()],
                    'label' => Translator::getInstance()->trans('Product base price excluding taxes *'),
                    'label_attr' => ['for' => 'price_without_tax'],
                ])
                ->add('tax_price', NumberType::class, [
                    'label' => Translator::getInstance()->trans('Product base price with taxes'),
                    'label_attr' => ['for' => 'price_with_tax'],
                ])
                ->add('currency', IntegerType::class, [
                    'constraints' => [new NotBlank()],
                    'label' => Translator::getInstance()->trans('Price currency *'),
                    'label_attr' => ['for' => 'currency_field'],
                ])
                ->add('tax_rule', IntegerType::class, [
                    'constraints' => [new NotBlank()],
                    'label' => Translator::getInstance()->trans('Tax rule for this product *'),
                    'label_attr' => ['for' => 'tax_rule_field'],
                ])
                ->add('weight', NumberType::class, [
                    'label' => Translator::getInstance()->trans('Weight'),
                    'label_attr' => ['for' => 'weight_field'],
                ])
                ->add('quantity', NumberType::class, [
                    'label' => Translator::getInstance()->trans('Stock'),
                    'label_attr' => ['for' => 'quantity_field'],
                    'required' => false,
                ])
                ->add('template_id', IntegerType::class, [
                    'label' => Translator::getInstance()->trans('Template'),
                    'label_attr' => ['for' => 'template_field'],
                    'required' => false,
                ])
            ;
        }
    }

    public function checkDuplicateRef($value, ExecutionContextInterface $context)
    {
        $count = ProductQuery::create()->filterByRef($value)->count();

        if ($count > 0) {
            $context->addViolation(
                Translator::getInstance()->trans(
                    'A product with reference %ref already exists. Please choose another reference.',
                    ['%ref' => $value]
                )
            );
        }
    }

    public static function getName()
    {
        return 'thelia_product_creation';
    }
}
