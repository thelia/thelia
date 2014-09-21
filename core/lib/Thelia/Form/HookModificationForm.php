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
class HookModificationForm extends HookCreationForm
{
    use StandardDescriptionFieldsTrait;

    protected function buildForm()
    {
        parent::buildForm(true);

        $this->formBuilder
            ->add("id", "hidden", array("constraints" => array(new GreaterThan(array('value' => 0)))))
            ->add("by_module", "checkbox", array(
                "label" => Translator::getInstance()->trans("By Module"),
                "label_attr" => array(
                    "for" => "by_module"
                )
            ))
            ->add("block", "checkbox", array(
                "label" => Translator::getInstance()->trans("Hook block"),
                "label_attr" => array(
                    "for" => "block"
                )
            ))

        ;

        // Add standard description fields, excluding title and locale, which a re defined in parent class
        $this->addStandardDescFields(array('title', 'postscriptum', 'locale'));
    }

    public function getName()
    {
        return "thelia_hook_modification";
    }
}
