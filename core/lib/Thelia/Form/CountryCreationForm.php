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

use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Validator\Constraints\NotBlank;

class CountryCreationForm extends BaseForm
{
    protected function buildForm(): void
    {
        $this->formBuilder
            ->add(
                'title',
                TextType::class,
                [
                    'constraints' => [
                        new NotBlank(),
                    ],
                    'label' => $this->translator->trans('Country title'),
                ]
            )
            ->add(
                'locale',
                HiddenType::class,
                [
                    'constraints' => [
                        new NotBlank(),
                    ],
                ]
            )
            ->add(
                'visible',
                CheckboxType::class,
                [
                    'required' => false,
                    'label' => $this->translator->trans('This country is online'),
                    'label_attr' => [
                        'for' => 'visible_create',
                    ],
                ]
            )
            ->add(
                'isocode',
                TextType::class,
                [
                    'constraints' => [
                        new NotBlank(),
                    ],
                    'label' => $this->translator->trans('Numerical ISO Code'),
                    'label_attr' => [
                        'help' => $this->translator->trans('Check country iso codes <a href="http://en.wikipedia.org/wiki/ISO_3166-1#Current_codes" target="_blank">here</a>.'),
                    ],
                ]
            )
            ->add(
                'isoalpha2',
                TextType::class,
                [
                    'constraints' => [
                        new NotBlank(),
                    ],
                    'label' => $this->translator->trans('ISO Alpha-2 code'),
                    'label_attr' => [
                        'help' => $this->translator->trans('Check country iso codes <a href="http://en.wikipedia.org/wiki/ISO_3166-1#Current_codes" target="_blank">here</a>.'),
                    ],
                ]
            )
            ->add(
                'isoalpha3',
                TextType::class,
                [
                    'constraints' => [
                        new NotBlank(),
                    ],
                    'label' => $this->translator->trans('ISO Alpha-3 code'),
                    'label_attr' => [
                        'help' => $this->translator->trans('Check country iso codes <a href="http://en.wikipedia.org/wiki/ISO_3166-1#Current_codes" target="_blank">here</a>.'),
                    ],
                ]
            )
            ->add(
                'has_states',
                CheckboxType::class,
                [
                    'required' => false,
                    'label' => $this->translator->trans('This country has states / provinces'),
                    'label_attr' => [
                        'for' => 'has_states_create',
                    ],
                ]
            )
        ;
    }

    public static function getName()
    {
        return 'thelia_country_creation';
    }
}
