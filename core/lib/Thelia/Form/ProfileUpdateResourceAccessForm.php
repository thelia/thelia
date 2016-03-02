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
                ),
            ))
        ;

        foreach (ResourceQuery::create()->find() as $resource) {
            $this->formBuilder->add(
                self::RESOURCE_ACCESS_FIELD_PREFIX.':'.str_replace(".", ":", $resource->getCode()),
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

                    ),
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
