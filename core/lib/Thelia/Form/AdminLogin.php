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
namespace Thelia\Form;

use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Thelia\Core\Translation\Translator;

class AdminLogin extends BaseForm
{
    protected function buildForm()
    {
        $this->formBuilder
            ->add("username", "text", array(
                "constraints" => array(
                    new NotBlank(),
                    new Length(array("min" => 3))
                ),
                "label" => Translator::getInstance()->trans("Username *"),
                "label_attr" => array(
                    "for" => "username"
                )
            ))
            ->add("password", "password", array(
                "constraints" => array(
                    new NotBlank()
                ),
                "label" => Translator::getInstance()->trans("Password *"),
                "label_attr" => array(
                    "for" => "password"
                )
            ))
            ->add("remember_me", "checkbox", array(
                    'value' => 'yes',
                    "label" => Translator::getInstance()->trans("Remember me ?"),
                    "label_attr" => array(
                        "for" => "remember_me"
                    )
            ))
            ;
    }

    public function getName()
    {
        return "thelia_admin_login";
    }
}
