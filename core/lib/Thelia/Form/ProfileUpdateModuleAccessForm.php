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
use Thelia\Core\Security\AccessManager;
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
                )
            ))
        ;

        foreach (ModuleQuery::create()->find() as $module) {
            $this->formBuilder->add(
                self::MODULE_ACCESS_FIELD_PREFIX . ':' . str_replace(".", ":", $module->getCode()),
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

                    )
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
            $context->addViolation("Profile ID not found");
        }
    }
}
