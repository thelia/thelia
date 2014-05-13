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

use Symfony\Component\Validator\Constraints\NotBlank;
use Thelia\Core\Translation\Translator;

/**
 * Class HookCreationForm
 * @package Thelia\Form
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class HookCreationForm extends BaseForm
{
    protected function buildForm()
    {
        $this->formBuilder
            ->add("code", "text", array(
                "constraints" => array(
                    new NotBlank()
                ),
                "label" => Translator::getInstance()->trans("Hook code *"),
                "label_attr" => array(
                    "for" => "code"
                )
            ))
            ->add("type", "integer", array(
                "label" => Translator::getInstance()->trans("Type"),
                "label_attr" => array(
                    "for" => "type"
                )
            ))
            ->add("native", "integer", array(
                "label" => Translator::getInstance()->trans("Native"),
                "label_attr" => array(
                    "for" => "native"
                )
            ))
            ->add("native", "integer", array(
                "label" => Translator::getInstance()->trans("Native"),
                "label_attr" => array(
                    "for" => "native"
                )
            ))
            ->add("active", "integer", array(
                "label" => Translator::getInstance()->trans("Native"),
                "label_attr" => array(
                    "for" => "active"
                )
            ))
            ->add("title", "text", array(
                "constraints" => array(
                    new NotBlank()
                ),
                "label" => Translator::getInstance()->trans("Hook title *"),
                "label_attr" => array(
                    "for" => "title"
                )
            ))
            ->add("description", "text", array(
                "constraints" => array(
                    new NotBlank()
                ),
                "label" => Translator::getInstance()->trans("Description"),
                "label_attr" => array("for" => "desciption")
            ))

        ;
    }

    public function getName()
    {
        return "thelia_hook_creation";
    }
}
