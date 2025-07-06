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
 * Class AreaDeleteCountryForm.
 *
 * @author  Franck Allimant <franck@cqfdev.fr>
 */
class AreaDeleteCountryForm extends BaseForm
{
    use CountryListValidationTrait;

    protected function buildForm(): void
    {
        $this->formBuilder
            ->add(
                'area_id',
                HiddenType::class,
                [
                    'required' => true,

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
                    'constraints' => [
                        new NotBlank(),
                        new Callback($this->verifyCountryList(...)),
                    ],
                    'allow_add' => true,
                    'allow_delete' => true,
                    'label' => Translator::getInstance()->trans('Countries'),
                    'label_attr' => [
                        'for' => 'country_delete_id',
                        'help' => Translator::getInstance()->trans(
                            'Select the countries to delete from this shipping zone'
                        ),
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
        return 'thelia_area_delete_country';
    }
}
