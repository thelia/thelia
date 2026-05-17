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

use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Validator\Constraints\GreaterThan;

class CountryModificationForm extends CountryCreationForm
{
    use StandardDescriptionFieldsTrait;

    protected function buildForm(): void
    {
        parent::buildForm();

        $this->formBuilder
            ->add('id', HiddenType::class, ['constraints' => [new GreaterThan(['value' => 0])]])
            ->add(
                'need_zip_code',
                CheckboxType::class,
                [
                    'required' => false,
                    'label' => $this->translator->trans('Addresses for this country need a zip code'),
                    'label_attr' => [
                        'for' => 'need_zip_code',
                    ],
                ],
            )
            ->add(
                'zip_code_format',
                TextType::class,
                [
                    'required' => false,
                    'constraints' => [],
                    'label' => $this->translator->trans('The zip code format'),
                    'label_attr' => [
                        'help' => $this->translator->trans(
                            'Use a N for a number, L for Letter, C for an iso code for the state.',
                        ),
                    ],
                ],
            );

        // Add standard description fields, excluding title and locale, which a re defined in parent class
        $this->addStandardDescFields(['title', 'locale']);
    }

    public static function getName(): string
    {
        return 'thelia_country_modification';
    }
}
