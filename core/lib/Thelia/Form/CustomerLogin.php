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
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Thelia\Core\Translation\Translator;
use Thelia\Model\CustomerQuery;

/**
 * Class CustomerLogin
 * @package Thelia\Form
 * @author  Manuel Raynaud <manu@raynaud.io>
 */
class CustomerLogin extends BruteforceForm
{
    protected function buildForm()
    {
        $this->formBuilder
            ->add("email", "email", array(
                "constraints" => array(
                    new Constraints\NotBlank(),
                    new Constraints\Email(),
                    new Constraints\Callback(array(
                        "methods" => array(
                            array($this, "verifyExistingEmail"),
                        ),
                    )),
                ),
                "label" => Translator::getInstance()->trans("Please enter your email address"),
                "label_attr" => array(
                    "for" => "email",
                ),
            ))
            ->add("account", "choice", array(
                "constraints" => array(
                    new Constraints\Callback(array(
                        "methods" => array(
                            array($this, "verifyAccount"),
                        ),
                    )),
                ),
                "choices" => array(
                    0 => Translator::getInstance()->trans("No, I am a new customer."),
                    1 => Translator::getInstance()->trans("Yes, I have a password :"),
                ),
                "label_attr" => array(
                    "for" => "account",
                ),
                "data" => 0,
            ))
            ->add("password", "password", array(
                "constraints" => array(
                    new Constraints\NotBlank(array(
                        'groups' => array('existing_customer'),
                    )),
                ),
                "label" => Translator::getInstance()->trans("Please enter your password"),
                "label_attr" => array(
                    "for" => "password",
                ),
                "required"    => false,
            ))
            ->add("remember_me", "checkbox", array(
                'value' => 'yes',
                "label" => Translator::getInstance()->trans("Remember me ?"),
                "label_attr" => array(
                    "for" => "remember_me",
                ),
            ))
        ;
    }

    /**
     * If the user select "Yes, I have a password", we check the password.
     */
    public function verifyAccount($value, ExecutionContextInterface $context)
    {
        if ($value == 1) {
            $data = $context->getRoot()->getData();
            if (false === $data['password'] || (empty($data['password']) && '0' != $data['password'])) {
                $context->getViolations()->add(new ConstraintViolation(
                    Translator::getInstance()->trans('This value should not be blank.'),
                    'account_password',
                    array(),
                    $context->getRoot(),
                    'children[password].data',
                    'propertyPath'
                ));
            }
        }
    }

    /**
     * If the user select "I'am a new customer", we make sure is email address does not exit in the database.
     */
    public function verifyExistingEmail($value, ExecutionContextInterface $context)
    {
        $data = $context->getRoot()->getData();
        if ($data["account"] == 0) {
            $customer = CustomerQuery::create()->findOneByEmail($value);
            if ($customer) {
                $context->addViolation(Translator::getInstance()->trans("A user already exists with this email address. Please login or if you've forgotten your password, go to Reset Your Password."));
            }
        }
    }

    public function getName()
    {
        return "thelia_customer_login";
    }
}
