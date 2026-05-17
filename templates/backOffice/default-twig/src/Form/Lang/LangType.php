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

namespace BackOfficeDefaultTwigBundle\Form\Lang;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\GreaterThan;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Contracts\Translation\TranslatorInterface;

final class LangType extends AbstractType
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
                'label' => $this->translator->trans('Language name'),
            ])
            ->add('code', TextType::class, [
                'constraints' => [new NotBlank()],
                'label' => $this->translator->trans('ISO 639-1 Code'),
                'help' => $this->translator->trans('Two-letter ISO 639-1 code, eg. fr, en'),
            ])
            ->add('locale', TextType::class, [
                'constraints' => [new NotBlank()],
                'label' => $this->translator->trans('Locale'),
                'help' => $this->translator->trans('Locale code, eg. fr_FR'),
            ])
            ->add('date_time_format', TextType::class, [
                'constraints' => [new NotBlank()],
                'label' => $this->translator->trans('Date/time format'),
            ])
            ->add('date_format', TextType::class, [
                'constraints' => [new NotBlank()],
                'label' => $this->translator->trans('Date format'),
            ])
            ->add('time_format', TextType::class, [
                'constraints' => [new NotBlank()],
                'label' => $this->translator->trans('Time format'),
            ])
            ->add('decimal_separator', TextType::class, [
                'constraints' => [new NotBlank()],
                'label' => $this->translator->trans('Decimal separator'),
            ])
            ->add('thousands_separator', TextType::class, [
                'trim' => false,
                'required' => false,
                'label' => $this->translator->trans('Thousands separator'),
            ])
            ->add('decimals', TextType::class, [
                'constraints' => [new NotBlank()],
                'label' => $this->translator->trans('Decimal places'),
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
            ->setDefaults([
                'include_id' => false,
                'csrf_token_id' => 'admin.lang',
            ])
            ->setAllowedTypes('include_id', 'bool');
    }
}
