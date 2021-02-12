<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Form;

use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Validator\Constraints;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Translation\Translator;
use Thelia\Model\ModuleQuery;
use Thelia\Model\ProfileQuery;

/**
 * Class ProfileUpdateModuleAccessForm
 * @package Thelia\Form
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 */
class ProfileUpdateModuleAccessForm extends BaseForm
{
    const MODULE_ACCESS_FIELD_PREFIX = "module";

    protected function buildForm()
    {
        $this->formBuilder
            ->add("id", HiddenType::class, [
                "required" => true,
                "constraints" => [
                    new Constraints\NotBlank(),
                    new Constraints\Callback(
                        [$this, "verifyProfileId"]
                    )
                ],
            ])
        ;

        foreach (ModuleQuery::create()->find() as $module) {
            $this->formBuilder->add(
                self::MODULE_ACCESS_FIELD_PREFIX.':'.str_replace(".", ":", $module->getCode()),
                ChoiceType::class,
                [
                    "choices" => [
                        AccessManager::VIEW => AccessManager::VIEW,
                        AccessManager::CREATE => AccessManager::CREATE,
                        AccessManager::UPDATE => AccessManager::UPDATE,
                        AccessManager::DELETE => AccessManager::DELETE,
                    ],
                    "attr" => [
                        "tag" => "modules",
                        "module_code" => $module->getCode(),
                    ],
                    "multiple" => true,
                    "constraints" => [
                    ],
                ]
            );
        }
    }

    public static function getName()
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
