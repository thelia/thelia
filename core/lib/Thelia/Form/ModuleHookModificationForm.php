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
use Symfony\Component\Validator\Constraints\GreaterThan;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\ExecutionContextInterface;
use Thelia\Core\Translation\Translator;
use Thelia\Model\Hook;
use Thelia\Model\HookQuery;

/**
 * Class HookModificationForm
 * @package Thelia\Form
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class ModuleHookModificationForm extends BaseForm
{
    protected function buildForm()
    {
        $this->formBuilder
            ->add("id", "hidden", array("constraints" => array(new GreaterThan(array('value' => 0)))))
            ->add("hook_id", "choice", array(
                "choices" => $this->getHookChoices(),
                "constraints" => array(
                    new NotBlank()
                ),
                "label" => Translator::getInstance()->trans("Hook"),
                "label_attr" => array("for" => "locale_create")
            ))
            ->add("classname", "text", array(
                "constraints" => array(
                    new NotBlank()
                ),
                "label" => Translator::getInstance()->trans("Service ID"),
                "label_attr" => array(
                    "for" => "classname"
                )
            ))
            ->add("method", "text", array(
                "label" => Translator::getInstance()->trans("Method Name"),
                "constraints" => array(
                    new NotBlank(),
                    new Callback(array("methods" => array(
                        array($this, "verifyMethod")
                    )))
                ),
                "label_attr" => array(
                    "for" => "method"
                )
            ))
            ->add("active", "checkbox", array(
                "label" => Translator::getInstance()->trans("Active"),
                "label_attr" => array(
                    "for" => "active"
                )
            ))
        ;
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
     * Verify if method has a valid signature. See RegisterListenersPass::isValidHookMethod for implementing this verification
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
        return "thelia_module_hook_modification";
    }
}
