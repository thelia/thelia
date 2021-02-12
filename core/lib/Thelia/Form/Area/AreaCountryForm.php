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

namespace Thelia\Form\Area;

use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\GreaterThan;
use Symfony\Component\Validator\Constraints\NotBlank;
use Thelia\Core\Translation\Translator;
use Thelia\Form\BaseForm;

/**
 * Class AreaCountryForm.
 *
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class AreaCountryForm extends BaseForm
{
    use CountryListValidationTrait;

    /**
     * {@inheritdoc}
     */
    protected function buildForm(): void
    {
        $this->formBuilder
            ->add(
                'area_id',
                HiddenType::class,
                [
                    'constraints' => [
                        new GreaterThan(['value' => 0]),
                        new NotBlank(),
                    ],
                ]
            )
            ->add(
                'country_id',
                CollectionType::class,
                [
                    'entry_type' => TextType::class,
                    'required' => true,
                    'constraints' => [
                        new NotBlank(),
                        new Callback([$this, 'verifyCountryList']),
                    ],
                    'allow_add' => true,
                    'allow_delete' => true,
                    'label' => Translator::getInstance()->trans('Countries'),
                    'label_attr' => [
                        'for' => 'countries-add',
                        'help' => Translator::getInstance()
                            ->trans('Select the countries to include in this shipping zone'),
                    ],
                    'attr' => [
                        'size' => 10,
                        'multiple' => true,
                    ],
                ]
            )
        ;
    }

    /**
     * @return string the name of you form. This name must be unique
     */
    public static function getName()
    {
        return 'thelia_area_country';
    }
}
