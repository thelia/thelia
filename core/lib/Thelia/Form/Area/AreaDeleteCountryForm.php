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

use Thelia\Core\Translation\Translator;
use Symfony\Component\Validator\Constraints\GreaterThan;
use Symfony\Component\Validator\Constraints\NotBlank;
use Thelia\Form\BaseForm;

/**
 * Class AreaDeleteCountryForm
 * @package Thelia\Form\Area
 * @author  Franck Allimant <franck@cqfdev.fr>
 */
class AreaDeleteCountryForm extends BaseForm
{
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
                    'required' => true,

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
                    'type'         => 'integer',
                    'allow_add'    => true,
                    'allow_delete' => true,
                    'label'        => Translator::getInstance()->trans('Countries'),
                    'label_attr'   => [
                        'for'         => 'products',
                        'help'        => Translator::getInstance()->trans('Select the countries to delete from this shipping zone'),
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
        return 'thelia_area_delete_country';
    }
}
