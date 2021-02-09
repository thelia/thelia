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

use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
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
        parent::buildForm();

        $this->formBuilder
            ->add("id", HiddenType::class, ["constraints" => [new GreaterThan(['value' => 0])]])
            ->add("by_module", CheckboxType::class, [
                "label" => Translator::getInstance()->trans("By Module"),
                "required" => false,
                "label_attr" => [
                    "for" => "by_module",
                    "help" => Translator::getInstance()->trans(
                        "This hook is specific to a module (delivery/payment modules)."
                    ),
                ],
            ])
            ->add("block", CheckboxType::class, [
                "label" => Translator::getInstance()->trans("Hook block"),
                "required" => false,
                "label_attr" => [
                    "for" => "block",
                    "help" => Translator::getInstance()->trans(
                        "If checked, this hook will be used by a hook block. If not, by hook function."
                    ),
                ],
            ])
        ;

        // Add standard description fields, excluding title and locale, which a re defined in parent class
        $this->addStandardDescFields(['title', 'postscriptum', 'locale']);
    }

    public function getName()
    {
        return "thelia_hook_modification";
    }
}
