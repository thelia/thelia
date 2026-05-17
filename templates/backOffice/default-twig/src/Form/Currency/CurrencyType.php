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

namespace BackOfficeDefaultTwigBundle\Form\Currency;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\GreaterThan;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\PositiveOrZero;
use Symfony\Contracts\Translation\TranslatorInterface;

final class CurrencyType extends AbstractType
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'constraints' => [new NotBlank()],
                'label' => $this->translator->trans('Name'),
            ])
            ->add('locale', TextType::class, [
                'constraints' => [new NotBlank()],
                'label' => $this->translator->trans('Locale'),
                'help' => $this->translator->trans('Locale code, eg. fr_FR'),
            ])
            ->add('code', TextType::class, [
                'constraints' => [new NotBlank()],
                'label' => $this->translator->trans('ISO 4217 code'),
                'help' => $this->translator->trans('Three-letter ISO 4217 code, eg. EUR, USD'),
            ])
            ->add('symbol', TextType::class, [
                'constraints' => [new NotBlank()],
                'label' => $this->translator->trans('Symbol'),
                'help' => $this->translator->trans('The symbol, such as $, £, €, ...'),
            ])
            ->add('format', TextType::class, [
                'constraints' => [new NotBlank()],
                'label' => $this->translator->trans('Format'),
                'help' => $this->translator->trans('%n for number, %c for the currency code, %s for the currency symbol'),
            ])
            ->add('rate', NumberType::class, [
                'constraints' => [new NotBlank(), new PositiveOrZero()],
                'scale' => 6,
                'html5' => true,
                'label' => $this->translator->trans('Exchange rate'),
                'help' => $this->translator->trans('Price in default currency × rate = price in this currency'),
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
                'csrf_token_id' => 'admin.currency',
            ])
            ->setAllowedTypes('include_id', 'bool');
    }
}
