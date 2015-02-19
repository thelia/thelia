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

class ContentCreationForm extends BaseForm
{
    protected function buildForm()
    {
        $this->formBuilder
            ->add("title", "text", array(
                "constraints" => array(
                    new NotBlank(),
                ),
                "label" => Translator::getInstance()->trans('Content title *'),
                "label_attr" => array(
                    "for" => "title",
                ),
            ))
            ->add("default_folder", "integer", array(
                "label" => Translator::getInstance()->trans("Default folder *"),
                "constraints" => array(
                    new NotBlank(),
                ),
                "label_attr" => array("for" => "default_folder"),
            ))
            ->add("locale", "text", array(
                "constraints" => array(
                    new NotBlank(),
                ),
            ))
            ->add("visible", "integer", array(
                "label" => Translator::getInstance()->trans("This content is online."),
                "label_attr" => array("for" => "visible_create"),
            ))
            ;
    }

    public function getName()
    {
        return "thelia_content_creation";
    }
}
