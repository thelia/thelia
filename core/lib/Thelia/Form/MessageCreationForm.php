<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : info@thelia.net                                                      */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      This program is free software; you can redistribute it and/or modify         */
/*      it under the terms of the GNU General Public License as published by         */
/*      the Free Software Foundation; either version 3 of the License                */
/*                                                                                   */
/*      This program is distributed in the hope that it will be useful,              */
/*      but WITHOUT ANY WARRANTY; without even the implied warranty of               */
/*      MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the                */
/*      GNU General Public License for more details.                                 */
/*                                                                                   */
/*      You should have received a copy of the GNU General Public License            */
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.         */
/*                                                                                   */
/*************************************************************************************/
namespace Thelia\Form;

use Symfony\Component\Validator\Constraints;
use Thelia\Model\MessageQuery;
use Symfony\Component\Validator\ExecutionContextInterface;
use Thelia\Core\Translation\Translator;

class MessageCreationForm extends BaseForm
{
    protected function buildForm($change_mode = false)
    {
        $name_constraints = array(new Constraints\NotBlank());

        if (!$change_mode) {
            $name_constraints[] = new Constraints\Callback(array(
                "methods" => array(array($this, "checkDuplicateName"))
            ));
        }

        $this->formBuilder
            ->add("name", "text", array(
                "constraints" => $name_constraints,
                "label" => Translator::getInstance()->trans('Name *'),
                "label_attr" => array(
                    "for" => "name"
                )
            ))
            ->add("title", "text", array(
                "constraints" => array(
                    new Constraints\NotBlank()
                ),
                "label" => Translator::getInstance()->trans('Purpose *'),
                "label_attr" => array(
                    "for" => "purpose"
                )
            ))
            ->add("locale", "hidden", array(
                "constraints" => array(
                    new Constraints\NotBlank()
                )
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
