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

use Symfony\Component\Validator\Constraints\NotBlank;

class CountryCreationForm extends BaseForm
{
    protected function buildForm()
    {
        $this->formBuilder
            ->add(
                'title',
                'text',
                [
                    'constraints' => [
                        new NotBlank(),
                    ],
                    'label' => $this->translator->trans('Country title')
                ]
            )
            ->add(
                'locale',
                'hidden',
                [
                    'constraints' => [
                        new NotBlank(),
                    ],
                ]
            )
            ->add(
                'visible',
                'checkbox',
                [
                    'required' => false,
                    'label' => $this->translator->trans('This country is online'),
                    'label_attr' => [
                        'for' => 'visible_create',
                    ]
                ]
            )
            ->add(
                'isocode',
                'text',
                [
                    'constraints' => [
                        new NotBlank(),
                    ],
                    'label' => $this->translator->trans('Numerical ISO Code'),
                    'label_attr' => [
                        'help' => $this->translator->trans('Check country iso codes <a href="http://en.wikipedia.org/wiki/ISO_3166-1#Current_codes" target="_blank">here</a>.')
                    ],
                ]
            )
            ->add(
                'isoalpha2',
                'text',
                [
                    'constraints' => [
                        new NotBlank(),
                    ],
                    'label' => $this->translator->trans('ISO Alpha-2 code'),
                    'label_attr' => [
                        'help' => $this->translator->trans('Check country iso codes <a href="http://en.wikipedia.org/wiki/ISO_3166-1#Current_codes" target="_blank">here</a>.')
                    ],
                ]
            )
            ->add(
                'isoalpha3',
                'text',
                [
                    'constraints' => [
                        new NotBlank(),
                    ],
                    'label' => $this->translator->trans('ISO Alpha-3 code'),
                    'label_attr' => [
                        'help' => $this->translator->trans('Check country iso codes <a href="http://en.wikipedia.org/wiki/ISO_3166-1#Current_codes" target="_blank">here</a>.')
                    ],
                ]
            )
            ->add(
                'has_states',
                'checkbox',
                [
                    'required' => false,
                    'label' => $this->translator->trans('This country has states / provinces'),
                    'label_attr' => [
                        'for' => 'has_states_create',
                    ]
                ]
            )
        ;
    }

    public function getName()
    {
        return "thelia_country_creation";
    }
}
