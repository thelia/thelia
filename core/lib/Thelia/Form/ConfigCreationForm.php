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
use Thelia\Model\ConfigQuery;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Thelia\Core\Translation\Translator;

class ConfigCreationForm extends BaseForm
{
    protected function buildForm($change_mode = false)
    {
        $name_constraints = array(new Constraints\NotBlank());

        if (!$change_mode) {
            $name_constraints[] = new Constraints\Callback(array(
                "methods" => array(array($this, "checkDuplicateName")),
            ));
        }

        $this->formBuilder
            ->add("name", "text", array(
                "constraints" => $name_constraints,
                "label" => Translator::getInstance()->trans('Name *'),
                "label_attr" => array(
                    "for" => "name",
                ),
            ))
            ->add("title", "text", array(
                "constraints" => array(
                    new Constraints\NotBlank(),
                ),
                "label" => Translator::getInstance()->trans('Purpose *'),
                "label_attr" => array(
                    "for" => "purpose",
                ),
            ))
            ->add("locale", "hidden", array(
                "constraints" => array(
                    new Constraints\NotBlank(),
                ),
            ))
            ->add("value", "text", array(
                "label" => Translator::getInstance()->trans('Value *'),
                "label_attr" => array(
                    "for" => "value",
                ),
            ))
            ->add("hidden", "hidden", array())
            ->add("secured", "hidden", array(
                "label" => Translator::getInstance()->trans('Prevent variable modification or deletion, except for super-admin'),
            ))
        ;
    }

    public function getName()
    {
        return "thelia_config_creation";
    }

    public function checkDuplicateName($value, ExecutionContextInterface $context)
    {
        $config = ConfigQuery::create()->findOneByName($value);

        if ($config) {
            $context->addViolation(Translator::getInstance()->trans('A variable with name "%name" already exists.', array('%name' => $value)));
        }
    }
}
