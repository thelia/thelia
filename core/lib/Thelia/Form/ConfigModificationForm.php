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
use Symfony\Component\Validator\Constraints\GreaterThan;
use Thelia\Core\Translation\Translator;

class ConfigModificationForm extends BaseForm
{
    use StandardDescriptionFieldsTrait;

    protected function buildForm()
    {
        $this->formBuilder
            ->add("id", "hidden", array(
                    "constraints" => array(
                        new GreaterThan(
                            array('value' => 0)
                        ),
                    ),
            ))
            ->add("name", "text", array(
                "constraints" => array(
                    new NotBlank(),
                ),
                "label" => Translator::getInstance()->trans('Name'),
                "label_attr" => array(
                    "for" => "name",
                ),
            ))
            ->add("value", "text", array(
                "label" => Translator::getInstance()->trans('Value'),
                "label_attr" => array(
                    "for" => "value",
                ),
            ))
            ->add("hidden", "hidden", array())
            ->add("secured", "hidden", array(
                "label" => Translator::getInstance()->trans('Prevent variable modification or deletion, except for super-admin'),
            ))
         ;

        // Add standard description fields
        $this->addStandardDescFields();
    }

    public function getName()
    {
        return "thelia_config_modification";
    }
}
