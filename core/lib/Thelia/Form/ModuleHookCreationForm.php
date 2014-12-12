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

use Propel\Runtime\ActiveQuery\Criteria;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\ExecutionContextInterface;
use Thelia\Core\Translation\Translator;
use Thelia\Model\Hook;
use Thelia\Model\HookQuery;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;

/**
 * Class HookCreationForm
 * @package Thelia\Form
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class ModuleHookCreationForm extends BaseForm
{
    /** @var Translator */
    protected $translator;

    protected function buildForm()
    {
        $this->formBuilder
            ->add(
                "module_id",
                "choice",
                array(
                    "choices" => $this->getModuleChoices(),
                    "constraints" => array(
                        new NotBlank()
                    ),
                    "label" => Translator::getInstance()->trans("Module"),
                    "label_attr" => array(
                        "for" => "module_id"
                    )
                )
            )
            ->add(
                "hook_id",
                "choice",
                array(
                    "choices" => $this->getHookChoices(),
                    "constraints" => array(
                        new NotBlank()
                    ),
                    "label" => Translator::getInstance()->trans("Hook"),
                    "label_attr" => array("for" => "locale_create")
                )
            )
            ->add(
                "classname",
                "text",
                array(
                    "constraints" => array(
                        new NotBlank()
                    ),
                    "label" => Translator::getInstance()->trans("Service ID"),
                    "label_attr" => array(
                        "for" => "classname",
                        "help" => Translator::getInstance()->trans(
                            "The service id that will handle the hook (defined in the config.xml file of the module)."
                        )
                    )
                )
            )
            ->add(
                "method",
                "text",
                array(
                    "label" => Translator::getInstance()->trans("Method Name"),
                    "constraints" => array(
                        new NotBlank(),
                        new Callback(
                            array(
                                "methods" => array(
                                    array($this, "verifyMethod")
                                )
                            )
                        )
                    ),
                    "label_attr" => array(
                        "for" => "method",
                        "help" => Translator::getInstance()->trans(
                            "The method name that will handle the hook event."
                        )
                    )
                )
            );
    }

    protected function trans($id, $parameters = [])
    {
        if (null === $this->translator) {
            $this->translator = Translator::getInstance();
        }

        return $this->translator->trans($id, $parameters);
    }

    protected function getModuleChoices()
    {
        $choices = array();
        $modules = ModuleQuery::getActivated();
        /** @var Module $module */
        foreach ($modules as $module) {
            $choices[$module->getId()] = $module->getTitle();
        }

        return $choices;
    }

    protected function getHookChoices()
    {
        $choices = array();
        $hooks = HookQuery::create()
            ->filterByActivate(true, Criteria::EQUAL)
            ->find();
        /** @var Hook $hook */
        foreach ($hooks as $hook) {
            $choices[$hook->getId()] = $hook->getTitle();
        }

        return $choices;
    }

    /**
     *
     * Check if method has a valid signature.
     * See RegisterListenersPass::isValidHookMethod for implementing this verification
     *
     * @param $value
     * @param  ExecutionContextInterface $context
     * @return bool
     */
    public function verifyMethod($value, ExecutionContextInterface $context)
    {
        return true;
    }

    public function getName()
    {
        return "thelia_module_hook_creation";
    }
}
