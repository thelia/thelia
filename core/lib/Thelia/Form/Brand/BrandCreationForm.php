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

namespace Thelia\Form\Brand;

use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Validator\Constraints\NotBlank;
use Thelia\Core\Translation\Translator;
use Thelia\Form\BaseForm;
use Thelia\Model\Lang;

/**
 * Class BrandCreationForm.
 *
 * @author  Franck Allimant <franck@cqfdev.fr>
 */
class BrandCreationForm extends BaseForm
{
    protected function doBuilForm($titleFieldHelpLabel): void
    {
        $this->formBuilder->add(
            'title',
            TextType::class,
            [
                'constraints' => [new NotBlank()],
                'required' => true,
                'label' => Translator::getInstance()->trans('Brand name'),
                'label_attr' => [
                    'for' => 'title',
                    'help' => $titleFieldHelpLabel,
                ],
                'attr' => [
                    'placeholder' => Translator::getInstance()->trans('The brand name or title'),
                ],
            ]
        )
        ->add(
            'locale',
            HiddenType::class,
            [
                'constraints' => [new NotBlank()],
                'required' => true,
            ]
        )
        // Is this brand online ?
        ->add(
            'visible',
            CheckboxType::class,
            [
                'required' => false,
                'label' => Translator::getInstance()->trans('This brand is online'),
                'label_attr' => [
                    'for' => 'visible_create',
                ],
            ]
        );
    }

    protected function buildForm(): void
    {
        $this->doBuilForm(
            Translator::getInstance()->trans(
                'Enter here the brand name in the default language (%title%)',
                ['%title%' => Lang::getDefaultLanguage()->getTitle()]
            )
        );
    }

    public static function getName()
    {
        return 'thelia_brand_creation';
    }
}
