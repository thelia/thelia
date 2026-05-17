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

namespace BackOfficeDefaultTwigBundle\Form\Customer;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Contracts\Translation\TranslatorInterface;

final class AddressType extends AbstractType
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $tr = $this->translator;

        $builder
            ->add('label', TextType::class, [
                'constraints' => [new NotBlank()],
                'label' => $tr->trans('Address label'),
            ])
            ->add('title', ChoiceType::class, [
                'choices' => $options['title_choices'],
                'constraints' => [new NotBlank()],
                'label' => $tr->trans('Title'),
                'placeholder' => false,
            ])
            ->add('firstname', TextType::class, [
                'constraints' => [new NotBlank()],
                'label' => $tr->trans('First name'),
            ])
            ->add('lastname', TextType::class, [
                'constraints' => [new NotBlank()],
                'label' => $tr->trans('Last name'),
            ])
            ->add('company', TextType::class, [
                'required' => false,
                'label' => $tr->trans('Company'),
            ])
            ->add('address1', TextType::class, [
                'constraints' => [new NotBlank()],
                'label' => $tr->trans('Street address'),
            ])
            ->add('address2', TextType::class, [
                'required' => false,
                'label' => $tr->trans('Address line 2'),
            ])
            ->add('address3', TextType::class, [
                'required' => false,
                'label' => $tr->trans('Address line 3'),
            ])
            ->add('zipcode', TextType::class, [
                'constraints' => [new NotBlank()],
                'label' => $tr->trans('Zip code'),
            ])
            ->add('city', TextType::class, [
                'constraints' => [new NotBlank()],
                'label' => $tr->trans('City'),
            ])
            ->add('country', ChoiceType::class, [
                'choices' => $options['country_choices'],
                'constraints' => [new NotBlank()],
                'label' => $tr->trans('Country'),
                'placeholder' => false,
            ])
            ->add('phone', TextType::class, [
                'required' => false,
                'label' => $tr->trans('Phone'),
            ])
            ->add('cellphone', TextType::class, [
                'required' => false,
                'label' => $tr->trans('Cellphone'),
            ])
            ->add('customer_id', HiddenType::class, [
                'constraints' => [new NotBlank()],
            ]);

        if ($options['include_id']) {
            $builder->add('id', HiddenType::class, [
                'constraints' => [new NotBlank()],
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setDefaults([
                'include_id' => false,
                'csrf_token_id' => 'admin.address',
            ])
            ->setRequired(['title_choices', 'country_choices'])
            ->setAllowedTypes('include_id', 'bool')
            ->setAllowedTypes('title_choices', 'array')
            ->setAllowedTypes('country_choices', 'array');
    }
}
