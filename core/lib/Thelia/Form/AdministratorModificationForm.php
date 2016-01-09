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

        $this->formBuilder->get('password')->setRequired(false);
        $this->formBuilder->get('password_confirm')->setRequired(false);
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

        if (null !== $administrator && $administrator->getId() != $data['id']) {
            $context->addViolation($this->translator->trans("This administrator login already exists"));
        }
    }

    public function verifyExistingEmail($value, ExecutionContextInterface $context)
    {
        $data = $context->getRoot()->getData();

        $administrator = AdminQuery::create()->findOneByEmail($value);

        if (null !== $administrator && $administrator->getId() != $data['id']) {
            $context->addViolation($this->translator->trans("An administrator with thie email address already exists"));
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
