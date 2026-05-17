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

namespace BackOfficeDefaultTwigBundle\Form\Configuration;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\GreaterThan;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Contracts\Translation\TranslatorInterface;

final class CountryType extends AbstractType
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'constraints' => [new NotBlank()],
                'label' => $this->translator->trans('Country name'),
            ])
            ->add('isocode', TextType::class, [
                'constraints' => [new NotBlank()],
                'label' => $this->translator->trans('ISO numeric code'),
            ])
            ->add('isoalpha2', TextType::class, [
                'constraints' => [new NotBlank()],
                'label' => $this->translator->trans('ISO 3166-1 alpha-2'),
            ])
            ->add('isoalpha3', TextType::class, [
                'constraints' => [new NotBlank()],
                'label' => $this->translator->trans('ISO 3166-1 alpha-3'),
            ])
            ->add('area', IntegerType::class, [
                'required' => false,
                'label' => $this->translator->trans('Shipping area'),
            ])
            ->add('visible', CheckboxType::class, [
                'required' => false,
                'label' => $this->translator->trans('This country is online'),
            ])
            ->add('has_states', CheckboxType::class, [
                'required' => false,
                'label' => $this->translator->trans('Manages states'),
            ])
            ->add('locale', HiddenType::class, [
                'constraints' => [new NotBlank()],
            ]);

        if ($options['include_id']) {
            $builder->add('id', HiddenType::class, [
                'constraints' => [new NotBlank(), new GreaterThan(0)],
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setDefaults(['include_id' => false, 'csrf_token_id' => 'admin.country'])
            ->setAllowedTypes('include_id', 'bool');
    }
}
