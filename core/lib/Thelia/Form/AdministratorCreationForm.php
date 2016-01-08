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
use Symfony\Component\Validator\ExecutionContextInterface;
use Thelia\Core\Translation\Translator;
use Thelia\Model\AdminQuery;
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
                    new Constraints\Email()
                ),
                "label" => $this->translator->trans("Email address"),
                "label_attr" => array(
                    "for" => "email",
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

        if (strlen($data["password"]) < 4) {
            $context->addViolation("password must be composed of at least 4 characters");
        }
    }

    public function verifyExistingLogin($value, ExecutionContextInterface $context)
    {
        $administrator = AdminQuery::create()->findOneByLogin($value);
        if ($administrator !== null) {
            $context->addViolation("This login already exists");
        }
    }

    public function getName()
    {
        return "thelia_admin_administrator_creation";
    }
}
