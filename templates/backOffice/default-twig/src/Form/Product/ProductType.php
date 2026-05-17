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

namespace BackOfficeDefaultTwigBundle\Form\Product;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\GreaterThan;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Contracts\Translation\TranslatorInterface;

final class ProductType extends AbstractType
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('ref', TextType::class, [
                'constraints' => [new NotBlank()],
                'label' => $this->translator->trans('Product reference'),
            ])
            ->add('title', TextType::class, [
                'constraints' => [new NotBlank()],
                'label' => $this->translator->trans('Product title'),
            ])
            ->add('default_category', IntegerType::class, [
                'constraints' => [new NotBlank()],
                'label' => $this->translator->trans('Default product category'),
            ])
            ->add('locale', HiddenType::class, [
                'constraints' => [new NotBlank()],
            ])
            ->add('visible', CheckboxType::class, [
                'required' => false,
                'label' => $this->translator->trans('This product is online'),
            ])
            ->add('virtual', CheckboxType::class, [
                'required' => false,
                'label' => $this->translator->trans('Virtual product (no physical shipping)'),
            ]);

        if (!$options['include_id']) {
            $builder
                ->add('price', NumberType::class, [
                    'constraints' => [new NotBlank()],
                    'label' => $this->translator->trans('Base price (excluding taxes)'),
                ])
                ->add('tax_price', NumberType::class, [
                    'required' => false,
                    'label' => $this->translator->trans('Base price with taxes'),
                ])
                ->add('currency', IntegerType::class, [
                    'constraints' => [new NotBlank()],
                    'label' => $this->translator->trans('Price currency'),
                ])
                ->add('weight', NumberType::class, [
                    'required' => false,
                    'label' => $this->translator->trans('Weight (kg)'),
                ])
                ->add('quantity', IntegerType::class, [
                    'required' => false,
                    'label' => $this->translator->trans('Stock'),
                ])
                ->add('tax_rule', IntegerType::class, [
                    'constraints' => [new NotBlank()],
                    'label' => $this->translator->trans('Tax rule'),
                ]);
        }

        if ($options['include_id']) {
            $builder
                ->add('id', HiddenType::class, [
                    'constraints' => [new NotBlank(), new GreaterThan(0)],
                ])
                ->add('template_id', IntegerType::class, [
                    'required' => false,
                    'label' => $this->translator->trans('Product template'),
                ])
                ->add('brand_id', IntegerType::class, [
                    'required' => false,
                    'label' => $this->translator->trans('Brand / Supplier'),
                ])
                ->add('virtual_document_id', IntegerType::class, [
                    'required' => false,
                    'label' => $this->translator->trans('Virtual document'),
                ])
                ->add('chapo', TextareaType::class, [
                    'required' => false,
                    'label' => $this->translator->trans('Summary'),
                ])
                ->add('description', TextareaType::class, [
                    'required' => false,
                    'label' => $this->translator->trans('Detailed description'),
                ])
                ->add('postscriptum', TextareaType::class, [
                    'required' => false,
                    'label' => $this->translator->trans('Conclusion'),
                ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setDefaults([
                'include_id' => false,
                'csrf_token_id' => 'admin.product',
            ])
            ->setAllowedTypes('include_id', 'bool');
    }
}
