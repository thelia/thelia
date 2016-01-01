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

namespace Thelia\Form\Area;

use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\GreaterThan;
use Symfony\Component\Validator\Constraints\NotBlank;
use Thelia\Core\Translation\Translator;
use Thelia\Form\BaseForm;

/**
 * Class AreaCountryForm
 * @package Thelia\Form\Area
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class AreaCountryForm extends BaseForm
{
    use CountryListValidationTrait;

    /**
     * @inheritdoc
     */
    protected function buildForm()
    {
        $this->formBuilder
            ->add(
                'area_id',
                'hidden',
                [
                    'constraints' => [
                        new GreaterThan(array('value' => 0)),
                        new NotBlank(),
                    ]
                ]
            )
            ->add(
                'country_id',
                'collection',
                [
                    'type' => 'text',
                    'required' => true,
                    'constraints' => [
                        new NotBlank(),
                        new Callback(["methods" => [[$this, "verifyCountryList"]]])
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
                    ]
                ]
            )
        ;
    }

    /**
     * @return string the name of you form. This name must be unique
     */
    public function getName()
    {
        return 'thelia_area_country';
    }
}
