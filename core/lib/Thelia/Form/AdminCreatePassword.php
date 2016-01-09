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

use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Thelia\Core\Translation\Translator;
use Thelia\Model\ConfigQuery;

class AdminCreatePassword extends BruteforceForm
{
    protected function buildForm()
    {
        $this->formBuilder
            ->add("password", "password", array(
                "constraints" => array(),
                "label" => $this->translator->trans("Password"),
                "label_attr" => array(
                    "for" => "password",
                ),
                "attr" => [
                    'placeholder' => Translator::getInstance()->trans('Enter the new password')
                ]
            ))
            ->add("password_confirm", "password", array(
                "constraints" => array(
                    new Callback(array("methods" => array(
                        array($this, "verifyPasswordField"),
                    ))),
                ),
                "label" => $this->translator->trans('Password confirmation'),
                "label_attr" => array(
                    "for" => "password_confirmation",
                ),
                "attr" => [
                    'placeholder' => Translator::getInstance()->trans('Enter the new password again')
                ]
            ))
        ;
    }

    public function verifyPasswordField($value, ExecutionContextInterface $context)
    {
        $data = $context->getRoot()->getData();

        if ($data["password"] === '' && $data["password_confirm"] === '') {
            $context->addViolation("password can't be empty");
        }

        if ($data["password"] != $data["password_confirm"]) {
            $context->addViolation("password confirmation is not the same as password field");
        }

        $minLength = ConfigQuery::getMinimuAdminPasswordLength();

        if (strlen($data["password"]) < $minLength) {
            $context->addViolation("password must be composed of at least $minLength characters");
        }
    }
}
