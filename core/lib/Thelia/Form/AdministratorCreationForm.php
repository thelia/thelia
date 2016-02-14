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
use Thelia\Core\Translation\Translator;
use Thelia\Model\AdminQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Model\LangQuery;
use Thelia\Model\ProfileQuery;

class AdministratorCreationForm extends BaseForm
{
    const PROFILE_FIELD_PREFIX = "profile";

    protected function buildForm()
    {
        $this->formBuilder
            ->add("login", "text", array(
                "constraints" => array(
                    new Constraints\NotBlank(),
                    new Constraints\Callback(array(
                        "methods" => array(
                            array($this, "verifyExistingLogin"),
                        ),
                    )),
                ),
                "label" => $this->translator->trans("Login name"),
                "label_attr" => array(
                    "for" => "login",
                    'help' => $this->translator->trans("This is the name used on the login screen")
                ),
            ))
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
                "label" => $this->translator->trans("Email address"),
                "label_attr" => array(
                    "for" => "email",
                    'help' => $this->translator->trans("Please enter a valid email address")
                ),
                'attr'        => [
                    'placeholder' => $this->translator->trans('Administrator email address'),
                ]
            ))
            ->add("firstname", "text", array(
                "constraints" => array(
                    new Constraints\NotBlank(),
                ),
                "label" => $this->translator->trans("First Name"),
                "label_attr" => array(
                    "for" => "firstname",
                ),
            ))
            ->add("lastname", "text", array(
                "constraints" => array(
                    new Constraints\NotBlank(),
                ),
                "label" => $this->translator->trans("Last Name"),
                "label_attr" => array(
                    "for" => "lastname",
                ),
            ))
            ->add("password", "password", array(
                "constraints" => array(),
                "label" => $this->translator->trans("Password"),
                "label_attr" => array(
                    "for" => "password",
                ),
            ))
            ->add("password_confirm", "password", array(
                "constraints" => array(
                    new Constraints\Callback(array("methods" => array(
                        array($this, "verifyPasswordField"),
                    ))),
                ),
                "label" => $this->translator->trans('Password confirmation'),
                "label_attr" => array(
                    "for" => "password_confirmation",
                ),
            ))
            ->add(
                'profile',
                "choice",
                array(
                    "choices" => ProfileQuery::getProfileList(),
                    "constraints" => array(
                        new Constraints\NotBlank(),
                    ),
                    "label" => $this->translator->trans('Profile'),
                    "label_attr" => array(
                        "for" => "profile",
                    ),
                )
            )
            ->add(
                'locale',
                "choice",
                array(
                    "choices" => $this->getLocaleList(),
                    "constraints" => array(
                        new Constraints\NotBlank(),
                    ),
                    "label" => $this->translator->trans('Preferred locale'),
                    "label_attr" => array(
                        "for" => "locale",
                    ),
                )
            )
        ;
    }

    protected function getLocaleList()
    {
        $locales = array();

        $list = LangQuery::create()->find();

        foreach ($list as $item) {
            $locales[$item->getLocale()] = $item->getLocale();
        }

        return $locales;
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

    public function verifyExistingLogin($value, ExecutionContextInterface $context)
    {
        if (null !== $administrator = AdminQuery::create()->findOneByLogin($value)) {
            $context->addViolation($this->translator->trans("This administrator login already exists"));
        }
    }

    public function verifyExistingEmail($value, ExecutionContextInterface $context)
    {
        if (null !== $administrator = AdminQuery::create()->findOneByEmail($value)) {
            $context->addViolation($this->translator->trans("An administrator with thie email address already exists"));
        }
    }

    public function getName()
    {
        return "thelia_admin_administrator_creation";
    }
}
