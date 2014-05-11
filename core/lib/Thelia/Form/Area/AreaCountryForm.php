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
 * Class AreaCountryForm
 * @package Thelia\Form\Area
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */
class AreaCountryForm extends BaseForm
{

    /**
     *
     * in this function you add all the fields you need for your Form.
     * Form this you have to call add method on $this->formBuilder attribute :
     *
     * $this->formBuilder->add("name", "text")
     *   ->add("email", "email", array(
     *           "attr" => array(
     *               "class" => "field"
     *           ),
     *           "label" => "email",
     *           "constraints" => array(
     *               new \Symfony\Component\Validator\Constraints\NotBlank()
     *           )
     *       )
     *   )
     *   ->add('age', 'integer');
     *
     * @return null
     */
    protected function buildForm()
    {
        $this->formBuilder
            ->add('area_id', 'integer', array(
                'constraints' => array(
                    new GreaterThan(array('value' => 0)),
                    new NotBlank()
                )

            ))
            ->add('country_id', 'integer', array(
                'constraints' => array(
                    new GreaterThan(array('value' => 0)),
                    new NotBlank()
                ),
                'label_attr' => array(
                    'for' => 'area_country'
                ),
                'label' => Translator::getInstance()->trans('Country')
            ));
    }

    /**
     * @return string the name of you form. This name must be unique
     */
    public function getName()
    {
        return 'thelia_area_country';
    }
}
