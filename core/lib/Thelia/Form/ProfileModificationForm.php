<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia                                                                       */
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
/*      along with this program. If not, see <http://www.gnu.org/licenses/>.         */
/*                                                                                   */
/*************************************************************************************/

namespace Thelia\Form;

use Symfony\Component\Validator\Constraints;
use Thelia\Core\Translation\Translator;
use Thelia\Model\ConfigQuery;

/**
 * Class ProfileModification
 * @package Thelia\Form
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */
class ProfileModificationForm extends BaseForm
{


    protected function buildForm()
    {

        $this->formBuilder
            ->add("firstname", "text", array(
                "constraints" => array(
                    new Constraints\NotBlank()
                ),
                "label" => Translator::getInstance()->trans("First Name"),
                "label_attr" => array(
                    "for" => "firstname"
                )
            ))
            ->add("lastname", "text", array(
                "constraints" => array(
                    new Constraints\NotBlank()
                ),
                "label" => Translator::getInstance()->trans("Last Name"),
                "label_attr" => array(
                    "for" => "lastname"
                )
            ))
            ->add("password", "password", array(
                "constraints" => array(
                    new Constraints\NotBlank(),
                    new Constraints\Length(array("min" => ConfigQuery::read("password.length", 4)))
                ),
                "label" => Translator::getInstance()->trans("Password"),
                "label_attr" => array(
                    "for" => "password"
                )
            ))
            ->add("password_confirm", "password", array(
                "constraints" => array(
                    new Constraints\NotBlank(),
                    new Constraints\Length(array("min" => ConfigQuery::read("password.length", 4))),
                    new Constraints\Callback(array("methods" => array(
                        array($this, "verifyPasswordField")
                    )))
                ),
                "label" => "Password confirmation",
                "label_attr" => array(
                    "for" => "password_confirmation"
                )
            ))
        ;
    }

    public function getName()
    {
        return "thelia_profile_modification";
    }
}
