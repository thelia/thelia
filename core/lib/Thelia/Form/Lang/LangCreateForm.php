<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Form\Lang;

use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Validator\Constraints\NotBlank;
use Thelia\Core\Translation\Translator;
use Thelia\Form\BaseForm;

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
     * $this->formBuilder->add("name", TextType::class)
     *   ->add("email", EmailType::class, array(
     *           "attr" => array(
     *               "class" => "field"
     *           ),
     *           "label" => "email",
     *           "constraints" => array(
     *               new \Symfony\Component\Validator\Constraints\NotBlank()
     *           )
     *       )
     *   )
     *   ->add('age', IntegerType::class);
     *
     * @return null
     */
    protected function buildForm()
    {
        $this->formBuilder
            ->add('title', TextType::class, [
                'constraints' => [
                    new NotBlank(),
                ],
                'label' => Translator::getInstance()->trans('Language name'),
                'label_attr' => [
                    'for' => 'title_lang',
                ],
            ])
            ->add('code', TextType::class, [
                'constraints' => [
                    new NotBlank(),
                ],
                'label' => Translator::getInstance()->trans('ISO 639-1 Code'),
                'label_attr' => [
                    'for' => 'code_lang',
                ],
            ])
            ->add('locale', TextType::class, [
                'constraints' => [
                    new NotBlank(),
                ],
                'label' => Translator::getInstance()->trans('language locale'),
                'label_attr' => [
                    'for' => 'locale_lang',
                ],
            ])
            ->add('date_time_format', TextType::class, [
                'constraints' => [
                    new NotBlank(),
                ],
                'label' => Translator::getInstance()->trans('date/time format'),
                'label_attr' => [
                    'for' => 'date_time_format',
                ],
            ])
            ->add('date_format', TextType::class, [
                'constraints' => [
                    new NotBlank(),
                ],
                'label' => Translator::getInstance()->trans('date format'),
                'label_attr' => [
                    'for' => 'date_lang',
                ],
            ])
            ->add('time_format', TextType::class, [
                'constraints' => [
                    new NotBlank(),
                ],
                'label' => Translator::getInstance()->trans('time format'),
                'label_attr' => [
                    'for' => 'time_lang',
                ],
            ])
            ->add('decimal_separator', TextType::class, [
                'constraints' => [
                    new NotBlank(),
                ],
                'label' => Translator::getInstance()->trans('decimal separator'),
                'label_attr' => [
                    'for' => 'decimal_separator',
                ],
            ])
            ->add('thousands_separator', TextType::class, [
                'trim' => false,
                'label' => Translator::getInstance()->trans('thousands separator'),
                'label_attr' => [
                    'for' => 'thousands_separator',
                ],
            ])
            ->add('decimals', TextType::class, [
                'constraints' => [
                    new NotBlank(),
                ],
                'label' => Translator::getInstance()->trans('Decimal places'),
                'label_attr' => [
                    'for' => 'decimals',
                ],
            ])
        ;
    }

    /**
     * @return string the name of you form. This name must be unique
     */
    public static function getName()
    {
        return 'thelia_language_create';
    }
}
