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
 * Class LangCreateForm
 * @package Thelia\Form\Lang
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class LangCreateForm extends BaseForm
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
            ->add('title', 'text', array(
                'constraints' => array(
                    new NotBlank(),
                ),
                'label' => Translator::getInstance()->trans('Language name'),
                'label_attr' => array(
                    'for' => 'title_lang',
                ),
            ))
            ->add('code', 'text', array(
                'constraints' => array(
                    new NotBlank(),
                ),
                'label' => Translator::getInstance()->trans('ISO 639-1 Code'),
                'label_attr' => array(
                    'for' => 'code_lang',
                ),
            ))
            ->add('locale', 'text', array(
                'constraints' => array(
                    new NotBlank(),
                ),
                'label' => Translator::getInstance()->trans('language locale'),
                'label_attr' => array(
                    'for' => 'locale_lang',
                ),
            ))
            ->add('date_time_format', 'text', array(
                'constraints' => array(
                    new NotBlank(),
                ),
                'label' => Translator::getInstance()->trans('date/time format'),
                'label_attr' => array(
                    'for' => 'date_time_format',
                ),
            ))
            ->add('date_format', 'text', array(
                'constraints' => array(
                    new NotBlank(),
                ),
                'label' => Translator::getInstance()->trans('date format'),
                'label_attr' => array(
                    'for' => 'date_lang',
                ),
            ))
            ->add('time_format', 'text', array(
                'constraints' => array(
                    new NotBlank(),
                ),
                'label' => Translator::getInstance()->trans('time format'),
                'label_attr' => array(
                    'for' => 'time_lang',
                ),
            ))
            ->add('decimal_separator', 'text', array(
                'constraints' => array(
                    new NotBlank(),
                ),
                'label' => Translator::getInstance()->trans('decimal separator'),
                'label_attr' => array(
                    'for' => 'decimal_separator',
                ),
            ))
            ->add('thousands_separator', 'text', array(
                'trim' => false,
                'label' => Translator::getInstance()->trans('thousands separator'),
                'label_attr' => array(
                    'for' => 'thousands_separator',
                ),
            ))
            ->add('decimals', 'text', array(
                'constraints' => array(
                    new NotBlank(),
                ),
                'label' => Translator::getInstance()->trans('Decimal places'),
                'label_attr' => array(
                    'for' => 'decimals',
                ),
            ))
        ;
    }

    /**
     * @return string the name of you form. This name must be unique
     */
    public function getName()
    {
        return 'thelia_language_create';
    }
}
