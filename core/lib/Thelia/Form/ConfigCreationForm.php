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

use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Validator\Constraints;
use Thelia\Model\ConfigQuery;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Thelia\Core\Translation\Translator;

class ConfigCreationForm extends BaseForm
{
    protected function buildForm()
    {
        $this->formBuilder
            ->add("name", TextType::class, array(
                "constraints" => array(
                    new Constraints\NotBlank(),
                    new Constraints\Callback(array(
                        "methods" => array(array($this, "checkDuplicateName")),
                    ))
                ),
                "label" => Translator::getInstance()->trans('Name *'),
                "label_attr" => array(
                    "for" => "name",
                ),
            ))
            ->add("title", TextType::class, array(
                "constraints" => array(
                    new Constraints\NotBlank(),
                ),
                "label" => Translator::getInstance()->trans('Purpose *'),
                "label_attr" => array(
                    "for" => "purpose",
                ),
            ))
            ->add("locale", HiddenType::class, array(
                "constraints" => array(
                    new Constraints\NotBlank(),
                ),
            ))
            ->add("value", TextType::class, array(
                "label" => Translator::getInstance()->trans('Value *'),
                "label_attr" => array(
                    "for" => "value",
                ),
            ))
            ->add("hidden", HiddenType::class, array())
            ->add("secured", HiddenType::class, array(
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
