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
                        )
                    )),
                ),
                "label" => Translator::getInstance()->trans("Login"),
                "label_attr" => array(
                    "for" => "login"
                ),
            ))
            ->add("firstname", "text", array(
                "constraints" => array(
                    new Constraints\NotBlank()
                ),
                "label" => Translator::getInstance()->trans("First Name"),
                "label_attr" => array(
                    "for" => "firstname"
                ),
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
                "constraints" => array(),
                "label" => Translator::getInstance()->trans("Password"),
                "label_attr" => array(
                    "for" => "password"
                ),
            ))
            ->add("password_confirm", "password", array(
                "constraints" => array(
                    new Constraints\Callback(array("methods" => array(
                        array($this, "verifyPasswordField")
                    )))
                ),
                "label" => Translator::getInstance()->trans('Password confirmation'),
                "label_attr" => array(
                    "for" => "password_confirmation"
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
                    "label" => Translator::getInstance()->trans('Profile'),
                    "label_attr" => array(
                        "for" => "profile"
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
                    "label" => Translator::getInstance()->trans('Preferred locale'),
                    "label_attr" => array(
                        "for" => "locale"
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
