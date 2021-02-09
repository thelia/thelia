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

use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
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
            ->add("login", TextType::class, [
                "constraints" => [
                    new Constraints\NotBlank(),
                    new Constraints\Callback(
                            [$this, "verifyExistingLogin"]),
                ],
                "label" => $this->translator->trans("Login name"),
                "label_attr" => [
                    "for" => "login",
                    'help' => $this->translator->trans("This is the name used on the login screen")
                ],
            ])
            ->add("email", EmailType::class, [
                "constraints" => [
                    new Constraints\NotBlank(),
                    new Constraints\Email(),
                    new Constraints\Callback(
                            [$this, "verifyExistingEmail"]
                        ),
                ],
                "label" => $this->translator->trans("Email address"),
                "label_attr" => [
                    "for" => "email",
                    'help' => $this->translator->trans("Please enter a valid email address")
                ],
                'attr'        => [
                    'placeholder' => $this->translator->trans('Administrator email address'),
                ]
            ])
            ->add("firstname", TextType::class, [
                "constraints" => [
                    new Constraints\NotBlank(),
                ],
                "label" => $this->translator->trans("First Name"),
                "label_attr" => [
                    "for" => "firstname",
                ],
            ])
            ->add("lastname", TextType::class, [
                "constraints" => [
                    new Constraints\NotBlank(),
                ],
                "label" => $this->translator->trans("Last Name"),
                "label_attr" => [
                    "for" => "lastname",
                ],
            ])
            ->add("password", PasswordType::class, [
                "constraints" => [],
                "label" => $this->translator->trans("Password"),
                "label_attr" => [
                    "for" => "password",
                ],
            ])
            ->add("password_confirm", PasswordType::class, [
                "constraints" => [
                    new Constraints\Callback([$this, "verifyPasswordField"]),
                ],
                "label" => $this->translator->trans('Password confirmation'),
                "label_attr" => [
                    "for" => "password_confirmation",
                ],
            ])
            ->add(
                'profile',
                ChoiceType::class,
                [
                    "choices" => ProfileQuery::getProfileList(),
                    "constraints" => [
                        new Constraints\NotBlank(),
                    ],
                    "label" => $this->translator->trans('Profile'),
                    "label_attr" => [
                        "for" => "profile",
                    ],
                ]
            )
            ->add(
                'locale',
                ChoiceType::class,
                [
                    "choices" => $this->getLocaleList(),
                    "constraints" => [
                        new Constraints\NotBlank(),
                    ],
                    "label" => $this->translator->trans('Preferred locale'),
                    "label_attr" => [
                        "for" => "locale",
                    ],
                ]
            )
        ;
    }

    protected function getLocaleList()
    {
        $locales = [];

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

        if (\strlen($data["password"]) < $minLength) {
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
