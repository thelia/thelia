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

use Symfony\Component\Validator\Constraints\GreaterThan;
use Thelia\Core\Translation\Translator;

/**
 * Class HookModificationForm
 * @package Thelia\Form
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class ModuleHookModificationForm extends BaseForm
{

    protected function buildForm()
    {
        parent::buildForm(true);

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
            ->add("classname", "checkbox", array(
                "constraints" => array(
                    new NotBlank()
                ),
                "label" => Translator::getInstance()->trans("Class name"),
                "label_attr" => array(
                    "for" => "classname"
                )
            ))
            ->add("method", "checkbox", array(
                "label" => Translator::getInstance()->trans("Method"),
                "constraints" => array(
                    new NotBlank()
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

    public function getName()
    {
        return "thelia_module_hook_modification";
    }

}
