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
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Thelia\Core\Form\Type\TheliaType;
use Thelia\Core\Translation\Translator;
use Thelia\Model\Tax;
use Thelia\TaxEngine\TaxEngine;
use Thelia\TaxEngine\TaxTypeRequirementDefinition;
use Thelia\Type\TypeInterface;

/**
 * Class TaxCreationForm
 * @package Thelia\Form
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 */
class TaxCreationForm extends BaseForm
{
    use StandardDescriptionFieldsTrait;

    protected static $typeList = [];

    protected function buildForm($change_mode = false)
    {
        if (!$this->container) {
            throw new \LogicException(
                Translator::getInstance()->trans(
                    "The container should not be null in this form. Please use the FormFactory to get an instance."
                )
            );
        }

        /** @var TaxEngine $taxEngine */
        $taxEngine = $this->container->get('thelia.taxEngine');

        $types = $taxEngine->getTaxTypeList();

        $typeList = array();
        $requirementList = array();

        foreach ($types as $classname) {
            $instance = new $classname();

            $typeList[Tax::escapeTypeName($classname)] = $instance->getTitle();

            $requirementList[$classname] = $instance->getRequirementsDefinition();
        }

        $this->formBuilder
            ->add(
                "locale",
                "hidden",
                [
                    "constraints" => [new NotBlank()],
                ]
            )
            ->add(
                "type",
                "choice",
                [
                    "choices" => $typeList,
                    "required" => true,
                    "constraints" => [
                        new Constraints\NotBlank(),
                    ],
                    "label" => Translator::getInstance()->trans("Type"),
                    "label_attr" => ["for" => "type_field"],
                ]
            )
        ;

        foreach ($requirementList as $name => $requirements) {
            /** @var TaxTypeRequirementDefinition $requirement */
            foreach ($requirements as $requirement) {
                if (!isset(self::$typeList[$requirement->getName()])) {
                    self::$typeList[$requirement->getName()] = $requirement->getType();
                }

                $this->formBuilder
                    // Replace the '\' in the class name by hyphens
                    // See TaxController::getRequirements if some changes are made about this.
                    ->add(
                        Tax::escapeTypeName($name) . ':' . $requirement->getName(),
                        new TheliaType(),
                        [
                            "constraints" => [
                                new Constraints\Callback(
                                    [
                                        "methods" => [
                                            [$this, "checkRequirementField"],
                                        ],
                                    ]
                                ),
                            ],
                            "attr" => [
                                "tag" => "requirements",
                                "tax_type" => Tax::escapeTypeName($name)
                            ],
                            "label_attr" => [
                                "type" => $requirement->getName()
                            ],
                            "label" => Translator::getInstance()->trans($requirement->getTitle()),
                            "type" => $requirement->getType()->getFormType(),
                            "options" => $requirement->getType()->getFormOptions(),
                        ]
                    );
            }
        }

        $this->addStandardDescFields(array('postscriptum', 'chapo', 'locale'));
    }

    public function checkRequirementField($value, ExecutionContextInterface $context)
    {
        $data = $context->getRoot()->getData();
        $type = $data["type"];

        if (false !== strpos($context->getPropertyPath(), $type)) {
            // extract requirement type
            if (preg_match('@\:(.+)\]@', $context->getPropertyPath(), $matches)) {
                $requirementType = $matches[1];
                if (isset(self::$typeList[$requirementType])) {
                    /** @var TypeInterface $typeClass */
                    $typeClass = self::$typeList[$requirementType];
                    $typeClass->verifyForm($value, $context);

                    return;
                }
            }

            $context->addViolation(
                Translator::getInstance()->trans(
                    "Impossible to check value `%value` for `%type` type",
                    [
                        '%value' => $value,
                        '%type' => $type
                    ]
                )
            );
        }
    }

    public function getName()
    {
        return "thelia_tax_creation";
    }
}
