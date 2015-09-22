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

namespace Thelia\Form\Lang;

use Symfony\Component\Validator\Constraints\NotBlank;
use Thelia\Form\BaseForm;
use Thelia\Core\Translation\Translator;

/**
 * Class LangDefaultBehaviorForm
 * @package Thelia\Form\Lang
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class LangDefaultBehaviorForm extends BaseForm
{
    /**
     *
     * in this function you add all the fields you need for your Form.
     * Form this you have to call add method on $this->formBuilder attribute :
     *
     * $this->formBuilder->add("name", "text")
     *   ->add("email", "email", array(
     *           "attr" => array(
     *               "class" => "field"
     *           ),
     *           "label" => "email",
     *           "constraints" => array(
     *               new \Symfony\Component\Validator\Constraints\NotBlank()
     *           )
     *       )
     *   )
     *   ->add('age', 'integer');
     *
     * @return null
     */
    protected function buildForm()
    {
        $this->formBuilder
            ->add('behavior', 'choice', array(
                'choices' => array(
                    0 => Translator::getInstance()->trans("Strictly use the requested language"),
                    1 => Translator::getInstance()->trans("Replace by the default language"),
                ),
                'constraints' => array(
                    new NotBlank(),
                ),
                'label' => Translator::getInstance()->trans("If a translation is missing or incomplete :"),
                'label_attr' => array(
                    'for' => 'defaultBehavior-form',
                ),
            ));
    }

    /**
     * @return string the name of you form. This name must be unique
     */
    public function getName()
    {
        return 'thelia_lang_defaultBehavior';
    }
}
