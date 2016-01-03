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
use Symfony\Component\Validator\Constraints\NotBlank;
use Thelia\Core\Translation\Translator;

class AttributeCreationForm extends BaseForm
{
    protected function buildForm()
    {
        $this->formBuilder
            ->add("title", "text", [
                "constraints" => [
                    new NotBlank(),
                ],
                "label"       => Translator::getInstance()->trans("Title *"),
                "label_attr"  => [
                    "for" => "title",
                ]
            ])
            ->add("locale", "text", [
                "constraints" => [
                    new NotBlank(),
                ]
            ])
            ->add("add_to_all", "checkbox", [
                "label"      => Translator::getInstance()->trans("Add to all product templates"),
                "label_attr" => [
                    "for" => "add_to_all",
                ]
            ]);
    }

    public function getName()
    {
        return "thelia_attribute_creation";
    }
}
