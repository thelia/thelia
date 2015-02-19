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

class FeatureCreationForm extends BaseForm
{
    protected function buildForm()
    {
        $this->formBuilder
            ->add(
                "title",
                "text",
                array(
                "constraints" => array(
                    new NotBlank(),
                ),
                "label" => Translator::getInstance()->trans("Title *"),
                "label_attr" => array(
                    "for" => "title",
                ), )
            )
            ->add(
                "locale",
                "text",
                array(
                "constraints" => array(
                    new NotBlank(),
                ), )
            )
            ->add(
                "add_to_all",
                "checkbox",
                array(
                "label" => Translator::getInstance()->trans("Add to all product templates"),
                "label_attr" => array(
                    "for" => "add_to_all",
                ), )
            )
        ;
    }

    public function getName()
    {
        return "thelia_feature_creation";
    }
}
