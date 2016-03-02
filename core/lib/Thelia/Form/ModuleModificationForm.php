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
use Thelia\Model\ModuleQuery;

class ModuleModificationForm extends BaseForm
{
    use StandardDescriptionFieldsTrait;

    protected function buildForm()
    {
        $this->addStandardDescFields();

        $this->formBuilder
            ->add("id", "hidden", array(
                "required" => true,
                "constraints" => array(
                    new Constraints\NotBlank(),
                    new Constraints\Callback(
                        array(
                            "methods" => array(
                                array($this, "verifyModuleId"),
                            ),
                        )
                    ),
                ),
                "attr" => array(
                    "id" => "module_update_id",
                ),
            ))
        ;
    }

    /**
     * @return string the name of you form. This name must be unique
     */
    public function getName()
    {
        return "thelia_admin_module_modification";
    }

    public function verifyModuleId($value, ExecutionContextInterface $context)
    {
        $module = ModuleQuery::create()
            ->findPk($value);

        if (null === $module) {
            $context->addViolation(Translator::getInstance()->trans("Module ID not found"));
        }
    }
}
