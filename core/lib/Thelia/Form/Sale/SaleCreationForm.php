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

namespace Thelia\Form\Sale;

use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Validator\Constraints\NotBlank;
use Thelia\Core\Translation\Translator;
use Thelia\Form\BaseForm;
use Thelia\Model\Lang;

/**
 * Class SaleCreationForm
 * @package Thelia\Form\Sale
 * @author  Franck Allimant <franck@cqfdev.fr>
 */
class SaleCreationForm extends BaseForm
{
    protected function doBuildForm($titleFieldHelpLabel)
    {
        $this->formBuilder->add(
            'title',
            TextType::class,
            [
                'constraints' => [ new NotBlank() ],
                'required'    => true,
                'label'       => Translator::getInstance()->trans('Sale title'),
                'label_attr'  => [
                    'for'         => 'title',
                    'help'        => $titleFieldHelpLabel,
                ],
                'attr' => [
                    'placeholder' => Translator::getInstance()->trans('The sale name or descriptive title'),
                ]
            ]
        )
        ->add(
            'label',
            TextType::class,
            [
                'constraints' => [ new NotBlank() ],
                'required'    => true,
                'label'       => Translator::getInstance()->trans('Sale announce label'),
                'label_attr'  => [
                    'for'         => 'label',
                    'help'        => Translator::getInstance()->trans('The sale announce label, such as Sales ! or Flash Sales !'),
                ],
                'attr' => [
                    'placeholder' => Translator::getInstance()->trans('Sale announce label'),
                ]
            ]
        )
        ->add(
            'locale',
            HiddenType::class,
            [
                'constraints' => [ new NotBlank() ],
                'required'    => true,
            ]
        );
    }

    protected function buildForm()
    {
        $this->doBuildForm(
            Translator::getInstance()->trans(
                'Enter here the sale name in the default language (%title%)',
                [ '%title%' => Lang::getDefaultLanguage()->getTitle()]
            )
        );
    }

    public static function getName()
    {
        return 'thelia_sale_creation';
    }
}
