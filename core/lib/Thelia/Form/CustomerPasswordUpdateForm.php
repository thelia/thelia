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

namespace Thelia\Form;

use Symfony\Component\Validator\Constraints;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Thelia\Model\ConfigQuery;
use Thelia\Core\Translation\Translator;
use Thelia\Model\CustomerQuery;

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
                            array($this, "verifyCurrentPasswordField"),
                        ))),
                    ),
                    "label" => Translator::getInstance()->trans("Current Password"),
                    "label_attr" => array(
                        "for" => "password_old",
                    ),
                ))
            ->add("password", "password", array(
                "constraints" => array(
                    new Constraints\NotBlank(),
                    new Constraints\Length(array("min" => ConfigQuery::read("password.length", 4))),
                ),
                "label" => Translator::getInstance()->trans("New Password"),
                "label_attr" => array(
                    "for" => "password",
                ),
            ))
            ->add("password_confirm", "password", array(
                "constraints" => array(
                    new Constraints\NotBlank(),
                    new Constraints\Length(array("min" => ConfigQuery::read("password.length", 4))),
                    new Constraints\Callback(array("methods" => array(
                        array($this, "verifyPasswordField"),
                    ))),
                ),
                "label" => Translator::getInstance()->trans('Password confirmation'),
                "label_attr" => array(
                    "for" => "password_confirmation",
                ),
            ));
    }

    public function verifyCurrentPasswordField($value, ExecutionContextInterface $context)
    {
        /**
         * Retrieve the user recording, because after the login action, the password is deleted in the session
         */
        $userId = $this->getRequest()->getSession()->getCustomerUser()->getId();
        $user = CustomerQuery::create()->findPk($userId);

        // Check if value of the old password match the password of the current user
        if (!password_verify($value, $user->getPassword())) {
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
