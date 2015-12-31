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
use Thelia\Core\Form\Type\TheliaType;
use Thelia\Core\Translation\Translator;
use Thelia\TaxEngine\TaxEngine;
use Thelia\Model\Tax;
use Thelia\Core\HttpFoundation\Request;

/**
 * Class TaxCreationForm
 * @package Thelia\Form
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 */
class TaxCreationForm extends BaseForm
{
    use StandardDescriptionFieldsTrait;

    protected function buildForm($change_mode = false)
    {
        if (! $this->container) {
            throw new \LogicException(Translator::getInstance()->trans("The container should not be null in this form. Please use the FormFactory to get an instance."));
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
            ->add("locale", "hidden", array(
                "constraints" => array(new NotBlank()),
            ))
            ->add("type", "choice", array(
                "choices" => $typeList,
                "required" => true,
                "constraints" => array(
                    new Constraints\NotBlank(),
                ),
                "label" => Translator::getInstance()->trans("Type"),
                "label_attr" => array("for" => "type_field"),
            ))
        ;

        foreach ($requirementList as $name => $requirements) {
            foreach ($requirements as $requirement) {
                $this->formBuilder
                    // Replace the '\' in the class name by hyphens
                    // See TaxController::getRequirements if some changes are made about this.
                    ->add(Tax::escapeTypeName($name).':'.$requirement->getName(), new TheliaType(), array(
                        //"instance" => $requirement->getType(),
                        "constraints" => array(
                            new Constraints\Callback(
                                array(
                                    "methods" => array(
                                        array($requirement->getType(), "verifyForm"),
                                    ),
                                )
                            ),
                        ),
                        "attr" => array(
                            "tag" => "requirements",
                            "tax_type" => Tax::escapeTypeName($name),
                        ),
                        "label" => Translator::getInstance()->trans($requirement->getName()),
                        "type" => $requirement->getType()->getFormType(),
                        "options" => $requirement->getType()->getFormOptions(),
                    ))
                ;
            }
        }

        $this->addStandardDescFields(array('postscriptum', 'chapo', 'locale'));
    }

    public function getName()
    {
        return "thelia_tax_creation";
    }
}
