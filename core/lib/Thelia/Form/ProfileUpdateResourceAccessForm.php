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
use Thelia\Core\Translation\Translator;
use Thelia\Model\ProfileQuery;
use Thelia\Model\ResourceQuery;

/**
 * Class ProfileUpdateResourceAccessForm
 * @package Thelia\Form
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 */
class ProfileUpdateResourceAccessForm extends BaseForm
{
    const RESOURCE_ACCESS_FIELD_PREFIX = "resource";

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

        foreach (ResourceQuery::create()->find() as $resource) {
            $this->formBuilder->add(
                self::RESOURCE_ACCESS_FIELD_PREFIX . ':' . str_replace(".", ":", $resource->getCode()),
                "choice",
                array(
                    "choices" => array(
                        AccessManager::VIEW => AccessManager::VIEW,
                        AccessManager::CREATE => AccessManager::CREATE,
                        AccessManager::UPDATE => AccessManager::UPDATE,
                        AccessManager::DELETE => AccessManager::DELETE,
                    ),
                    "attr" => array(
                        "tag" => "resources",
                        "resource_code" => $resource->getCode(),
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
        return "thelia_profile_resource_access_modification";
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
