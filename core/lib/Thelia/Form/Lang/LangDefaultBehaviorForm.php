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

namespace Thelia\Form\Lang;

use Symfony\Component\Validator\Constraints\NotBlank;
use Thelia\Form\BaseForm;
use Thelia\Core\Translation\Translator;

/**
 * Class LangDefaultBehaviorForm
 * @package Thelia\Form\Lang
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */
class LangDefaultBehaviorForm extends BaseForm
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
            ->add('behavior', 'choice', array(
                'choices' => array(
                    0 => Translator::getInstance()->trans("Strictly use the requested language"),
                    1 => Translator::getInstance()->trans("Replace by the default language"),
                ),
                'constraints' => array(
                    new NotBlank()
                ),
                'label' => Translator::getInstance()->trans("If a translation is missing or incomplete :"),
                'label_attr' => array(
                    'for' => 'defaultBehavior-form'
                )
            ));
    }

    /**
     * @return string the name of you form. This name must be unique
     */
    public function getName()
    {
        return 'thelia_lang_defaultBehavior';
    }
}
