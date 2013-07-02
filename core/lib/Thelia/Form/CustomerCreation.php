<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*	email : info@thelia.net                                                          */
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

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints;
use Symfony\Component\Validator\ExecutionContext;
use Symfony\Component\Validator\ExecutionContextInterface;
use Thelia\Model\ConfigQuery;
use Thelia\Model\CustomerQuery;


class CustomerCreation extends BaseForm
{

    protected function buildForm()
    {
        $this->form
            ->add("firstname", "text", array(
                "constraints" => array(
                    new Constraints\NotBlank()
                ),
                "label" => "firstname"
            ))
            ->add("lastname", "text", array(
                "constraints" => array(
                    new Constraints\NotBlank()
                ),
                "label" => "lastname"
            ))
            ->add("address1", "text", array(
                "constraints" => array(
                    new Constraints\NotBlank()
                ),
                "label" => "address"
            ))
            ->add("address2", "text", array(
                "label" => "Address Line 2"
            ))
            ->add("address3", "text", array(
                "label" => "Address Line 3"
            ))
            ->add("phone", "text", array(
                "label" => "phone"
            ))
            ->add("cellphone", "text", array(
                "label" => "cellphone"
            ))
            ->add("zipcode", "text", array(
                "constraints" => array(
                    new Constraints\NotBlank()
                ),
                "label" => "zipcode"
            ))
            ->add("city", "text", array(
                "constraints" => array(
                    new Constraints\NotBlank()
                ),
                "label" => "city"
            ))
            ->add("country", "text", array(
                "constraints" => array(
                    new Constraints\NotBlank()
                ),
                "label" => "country"
            ))
            ->add("title", "text", array(
                "constraints" => array(
                    new Constraints\NotBlank()
                ),
                "label" => "title"
            ))
            ->add("email", "email", array(
                "constraints" => array(
                    new Constraints\NotBlank(),
                    new Constraints\Email(),
                    new Constraints\Callback(array(
                        "methods" => array(
                            array($this,
                            "verifyExistingEmail")
                        )
                    ))
                ),
                "label" => "email"
            ))
            ->add("email_confirm", "email", array(
                "constraints" => array(
                    new Constraints\Callback(array(
                        "methods" => array(
                            array($this,
                            "verifyEmailField")
                        )
                    ))
                ),
                "label" => "email confirmation"
            ))
            ->add("password", "password", array(
                "constraints" => array(
                    new Constraints\Length(array("min" => ConfigQuery::read("password.length", 4)))
                ),
                "label" => "password"
            ))
            ->add("password_confirm", "password", array(
                "constraints" => array(
                    new Constraints\Length(array("min" => ConfigQuery::read("password.length", 4))),
                    new Constraints\Callback(array("methods" => array(
                        array($this, "verifyPasswordField")
                    )))
                ),
                "label" => "password confirmation"
            ))

        ;
    }

    public function verifyPasswordField($value, ExecutionContextInterface $context)
    {
        $data = $context->getRoot()->getData();

        if ($data["password"] != $data["password_confirm"]) {
            $context->addViolation("password confirmation is not the same as password field");
        }
    }

    public function verifyEmailField($value, ExecutionContextInterface $context)
    {
        $data = $context->getRoot()->getData();

        if ($data["email"] != $data["email_confirm"]) {
            $context->addViolation("email confirmation is not the same as email field");
        }
    }

    public function verifyExistingEmail($value, ExecutionContextInterface $context)
    {
        if (CustomerQuery::create()->filterByEmail($value)->exists()) {
            $context->addViolation("This email already exists");
        }
    }

    public function getName()
    {
        return "customerCreation";
    }
}