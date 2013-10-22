<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : info@thelia.net                                                      */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      This program is free software; you can redistribute it and/or modify         */
/*      it under the terms of the GNU General Public License as published by         */
/*      the Free Software Foundation; either version 3 of the License                */
/*                                                                                   */
/*      This program is distributed in the hope that it will be useful,              */
/*      but WITHOUT ANY WARRANTY; without even the implied warranty of               */
/*      MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the                */
/*      GNU General Public License for more details.                                 */
/*                                                                                   */
/*      You should have received a copy of the GNU General Public License            */
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.         */
/*                                                                                   */
/*************************************************************************************/

namespace Thelia\Form\Area;

use Symfony\Component\Validator\Constraints\GreaterThan;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\NotBlank;
use Thelia\Form\BaseForm;
use Thelia\Core\Translation\Translator;

/**
 * Class AreaPostageForm
 * @package Thelia\Form\Area
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */
class AreaPostageForm extends BaseForm
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
            ->add('postage', 'number', array(
                'constraints' => array(
                    new GreaterThanOrEqual(array('value' => 0)),
                    new NotBlank()
                ),
                'label_attr' => array('for' => 'area_postage'),
                'label' => Translator::getInstance()->trans('Postage')
            ))
        ;
    }

    /**
     * @return string the name of you form. This name must be unique
     */
    public function getName()
    {
        return 'thelia_area_postage';
    }
}
