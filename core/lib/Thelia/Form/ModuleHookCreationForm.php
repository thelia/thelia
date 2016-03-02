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
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Thelia\Core\Hook\BaseHook;
use Thelia\Core\Translation\Translator;
use Thelia\Model\Base\ModuleHookQuery;
use Thelia\Model\Hook;
use Thelia\Model\HookQuery;
use Thelia\Model\IgnoredModuleHookQuery;
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
                        new NotBlank(),
                    ),
                    "label" => $this->trans("Module"),
                    "label_attr" => array(
                        "for" => "module_id",
                        "help" => $this->trans(
                            "Only hookable modules are displayed in this menu."
                        ),
                    ),
                )
            )
            ->add(
                "hook_id",
                "choice",
                array(
                    "choices" => $this->getHookChoices(),
                    "constraints" => array(
                        new NotBlank(),
                    ),
                    "label" => $this->trans("Hook"),
                    "label_attr" => array("for" => "hook_id"),
                )
            )
            ->add(
                "classname",
                "text",
                array(
                    "constraints" => array(
                        new NotBlank(),
                    ),
                    "label" => $this->trans("Service ID"),
                    "label_attr" => array(
                        "for" => "classname",
                        "help" => $this->trans(
                            "The service id that will handle the hook (defined in the config.xml file of the module)."
                        ),
                    ),
                )
            )
            ->add(
                "method",
                "text",
                array(
                    "label" => $this->trans("Method Name"),
                    "constraints" => array(
                        new NotBlank(),
                        new Callback(
                            array(
                                "methods" => array(
                                    array($this, "verifyMethod"),
                                ),
                            )
                        ),
                    ),
                    "label_attr" => array(
                        "for" => "method",
                        "help" => $this->trans(
                            "The method name that will handle the hook event."
                        ),
                    ),
                )
            )
            ->add(
                "templates",
                "text",
                array(
                    "label" => $this->trans("Automatic rendered templates"),
                    "constraints" => array(
                        new Callback(
                            array(
                                "methods" => array(
                                    array($this, "verifyTemplates"),
                                ),
                            )
                        ),
                    ),
                    "label_attr" => array(
                        "for" => "templates",
                        "help" => $this->trans(
                            "When using the %method% method you can automatically render or dump templates or add CSS and JS files (e.g.: render:mytemplate.html;js:assets/js/myjs.js)",
                            ["%method%" => BaseHook::INJECT_TEMPLATE_METHOD_NAME]
                        ),
                    ),
                    "required" => false
                )
            )
        ;
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
            // Check if module defines a hook ID
            if (ModuleHookQuery::create()->filterByModuleId($module->getId())->count() > 0
                ||
                IgnoredModuleHookQuery::create()->filterByModuleId($module->getId())->count() > 0
            ) {
                $choices[$module->getId()] = $module->getTitle();
            }
        }

        asort($choices);

        return $choices;
    }

    protected function getHookChoices()
    {
        $choices = array();
        $hooks = HookQuery::create()
            ->filterByActivate(true, Criteria::EQUAL)
            ->joinWithI18n($this->translator->getLocale())
            ->orderBy('HookI18n.title', Criteria::ASC)
            ->find();

        /** @var Hook $hook */
        foreach ($hooks as $hook) {
            $choices[$hook->getId()] = $hook->getTitle().' (code '.$hook->getCode().')';
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
        if (! $this->hasContainer()) {
            return true;
        }

        $data = $context->getRoot()->getData();

        if (null === $service = $this->container->get($data["classname"])) {
            return true;
        }

        if (!method_exists($service, $data['method'])) {
            $context->addViolation(
                $this->trans(
                    "The method %method% doesn't exist in classname %classname%",
                    [
                        '%method%' => $data['method'],
                        '%classname%' => $data['classname']
                    ]
                )
            );
        }
    }

    /**
     * Check if method is the right one if we want to use automatic inserted templates .
     *
     * @param $value
     * @param  ExecutionContextInterface $context
     *
     * @return bool
     */
    public function verifyTemplates($value, ExecutionContextInterface $context)
    {
        $data = $context->getRoot()->getData();

        if (!empty($data['templates']) && $data['method'] !== BaseHook::INJECT_TEMPLATE_METHOD_NAME) {
            $context->addViolation(
                $this->trans(
                    "If you use automatic insert templates, you should use the method %method%",
                    [
                        '%method%' => BaseHook::INJECT_TEMPLATE_METHOD_NAME
                    ]
                )
            );
        }
    }

    public function getName()
    {
        return "thelia_module_hook_creation";
    }
}
