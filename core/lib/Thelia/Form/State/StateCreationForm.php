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

namespace Thelia\Form\State;

use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Validator\Constraints\NotBlank;
use Thelia\Core\Form\Type\Field\CountryIdType;
use Thelia\Form\BaseForm;

/**
 * Class StateCreationForm.
 *
 * @author Julien Chanséaume <julien@thelia.net>
 */
class StateCreationForm extends BaseForm
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
                    'label' => $this->translator->trans('State title'),
                ]
            )
            ->add('country_id', CountryIdType::class, [
                'constraints' => [
                    new NotBlank(),
                ],
                'label' => $this->translator->trans('Country'),
                'label_attr' => [
                    'for' => 'country',
                ],
            ])
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
                    'label' => $this->translator->trans('This state is online'),
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
                    'label' => $this->translator->trans('ISO Code'),
                    'label_attr' => [
                        'help' => $this->translator->trans('Iso code for states. It depends of the country.'),
                    ],
                ]
            )
        ;
    }

    public static function getName(): string
    {
        return 'thelia_state_creation';
    }
}
