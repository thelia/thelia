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
class ModuleHookModificationForm extends ModuleHookCreationForm
{
    protected function buildForm()
    {
        parent::buildForm();

        $this->formBuilder
            ->add("id", HiddenType::class, ["constraints" => [new GreaterThan(['value' => 0])]])
            ->add("active", CheckboxType::class, [
                "label" => Translator::getInstance()->trans("Active"),
                "required" => false,
                "label_attr" => [
                    "for" => "active",
                ],
            ])
        ;
    }

    public function getName()
    {
        return "thelia_module_hook_modification";
    }
}
