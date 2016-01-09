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
use Thelia\Model\Lang;
use Thelia\Model\MessageQuery;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Thelia\Core\Translation\Translator;

class MessageCreationForm extends BaseForm
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
                "label" => Translator::getInstance()->trans('Name'),
                "label_attr" => array(
                    "for" => "name",
                    'help' => Translator::getInstance()->trans("This is an identifier that will be used in the code to get this message"),
                ),
                'attr' => [
                    'placeholder' => Translator::getInstance()->trans("Mail template name"),
                ],
            ))
            ->add("title", "text", array(
                "constraints" => array(
                    new Constraints\NotBlank(),
                ),
                "label" => Translator::getInstance()->trans('Purpose'),
                "label_attr" => array(
                    "for" => "purpose",
                    'help' => Translator::getInstance()->trans(
                        "Enter here the mail template purpose in the default language (%title%)",
                        [ '%title%' => Lang::getDefaultLanguage()->getTitle() ]
                    ),
                ),
                'attr' => [
                    'placeholder' => Translator::getInstance()->trans("Mail template purpose"),
                ],
            ))
            ->add("locale", "hidden", array(
                "constraints" => array(
                    new Constraints\NotBlank(),
                ),
            ))
            ->add("secured", "hidden", array())
        ;
    }

    public function getName()
    {
        return "thelia_message_creation";
    }

    public function checkDuplicateName($value, ExecutionContextInterface $context)
    {
        $message = MessageQuery::create()->findOneByName($value);

        if ($message) {
            $context->addViolation(Translator::getInstance()->trans('A message with name "%name" already exists.', array('%name' => $value)));
        }
    }
}
