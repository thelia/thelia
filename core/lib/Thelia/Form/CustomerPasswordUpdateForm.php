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
use Symfony\Component\Validator\ExecutionContextInterface;
use Thelia\Model\ConfigQuery;
use Thelia\Core\Translation\Translator;

/**
 * Class CustomerPasswordUpdateForm
 * @package Thelia\Form
 * @author Christophe Laffont <claffont@openstudio.fr>
 */
class CustomerPasswordUpdateForm extends BaseForm
{

    protected function buildForm()
    {
        $this->formBuilder

            // Login Information
            ->add("password_old", "password", array(
                    "constraints" => array(
                        new Constraints\NotBlank(),
                        new Constraints\Callback(array("methods" => array(
                            array($this, "verifyCurrentPasswordField")
                        )))
                    ),
                    "label" => Translator::getInstance()->trans("Current Password"),
                    "label_attr" => array(
                        "for" => "password_old"
                    )
                ))
            ->add("password", "password", array(
                "constraints" => array(
                    new Constraints\NotBlank(),
                    new Constraints\Length(array("min" => ConfigQuery::read("password.length", 4)))
                ),
                "label" => Translator::getInstance()->trans("New Password"),
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
                "label" => Translator::getInstance()->trans('Password confirmation'),
                "label_attr" => array(
                    "for" => "password_confirmation"
                )
            ));
    }

    public function verifyCurrentPasswordField($value, ExecutionContextInterface $context)
    {
        // Check if value of the old password match the password of the current user
        if (!password_verify($value, $this->getRequest()->getSession()->getCustomerUser()->getPassword())) {
            $context->addViolation(Translator::getInstance()->trans("Your current password does not match."));
        }
    }

    public function verifyPasswordField($value, ExecutionContextInterface $context)
    {
        $data = $context->getRoot()->getData();

        if ($data["password"] != $data["password_confirm"]) {
            $context->addViolation(Translator::getInstance()->trans("password confirmation is not the same as password field"));
        }
    }

    public function getName()
    {
        return "thelia_customer_password_update";
    }
}
