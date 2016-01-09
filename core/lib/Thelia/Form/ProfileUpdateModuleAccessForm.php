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
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Translation\Translator;
use Thelia\Model\ProfileQuery;
use Thelia\Model\ModuleQuery;

/**
 * Class ProfileUpdateModuleAccessForm
 * @package Thelia\Form
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 */
class ProfileUpdateModuleAccessForm extends BaseForm
{
    const MODULE_ACCESS_FIELD_PREFIX = "module";

    protected function buildForm($change_mode = false)
    {
        $this->formBuilder
            ->add("id", "hidden", array(
                "required" => true,
                "constraints" => array(
                    new Constraints\NotBlank(),
                    new Constraints\Callback(
                        array(
                            "methods" => array(
                                array($this, "verifyProfileId"),
                            ),
                        )
                    ),
                ),
            ))
        ;

        foreach (ModuleQuery::create()->find() as $module) {
            $this->formBuilder->add(
                self::MODULE_ACCESS_FIELD_PREFIX.':'.str_replace(".", ":", $module->getCode()),
                "choice",
                array(
                    "choices" => array(
                        AccessManager::VIEW => AccessManager::VIEW,
                        AccessManager::CREATE => AccessManager::CREATE,
                        AccessManager::UPDATE => AccessManager::UPDATE,
                        AccessManager::DELETE => AccessManager::DELETE,
                    ),
                    "attr" => array(
                        "tag" => "modules",
                        "module_code" => $module->getCode(),
                    ),
                    "multiple" => true,
                    "constraints" => array(

                    ),
                )
            );
        }
    }

    public function getName()
    {
        return "thelia_profile_module_access_modification";
    }

    public function verifyProfileId($value, ExecutionContextInterface $context)
    {
        $profile = ProfileQuery::create()
            ->findPk($value);

        if (null === $profile) {
            $context->addViolation(Translator::getInstance()->trans("Profile ID not found"));
        }
    }
}
