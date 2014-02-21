<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*	    email : info@thelia.net                                                      */
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

class AdministratorModificationForm extends AdministratorCreationForm
{
    protected function buildForm()
    {
        parent::buildForm();

        $this->formBuilder
            ->add("id", "hidden", array(
                "required" => true,
                "constraints" => array(
                    new Constraints\NotBlank(),
                    new Constraints\Callback(
                        array(
                            "methods" => array(
                                array($this, "verifyAdministratorId"),
                            ),
                        )
                    ),
                ),
                "attr" => array(
                    "id" => "administrator_update_id",
                ),
            ))
        ;
    }

    /**
     * @return string the name of you form. This name must be unique
     */
    public function getName()
    {
        return "thelia_admin_administrator_modification";
    }

    public function verifyAdministratorId($value, ExecutionContextInterface $context)
    {
        $administrator = AdminQuery::create()
            ->findPk($value);

        if (null === $administrator) {
            $context->addViolation(Translator::getInstance()->trans("Administrator ID not found"));
        }
    }

    public function verifyExistingLogin($value, ExecutionContextInterface $context)
    {
        $data = $context->getRoot()->getData();

        $administrator = AdminQuery::create()->findOneByLogin($value);
        if ($administrator !== null && $administrator->getId() != $data['id']) {
            $context->addViolation(Translator::getInstance()->trans("This login already exists"));
        }
    }

    public function verifyPasswordField($value, ExecutionContextInterface $context)
    {
        $data = $context->getRoot()->getData();

        if ($data["password"] != $data["password_confirm"]) {
            $context->addViolation(Translator::getInstance()->trans("password confirmation is not the same as password field"));
        }

        if ($data["password"] !== '' && strlen($data["password"]) < 4) {
            $context->addViolation(Translator::getInstance()->trans("password must be composed of at least 4 characters"));
        }
    }
}
