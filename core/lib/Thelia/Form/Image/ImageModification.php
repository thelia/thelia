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

namespace Thelia\Form\Image;

use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Validator\Constraints\Image;
use Thelia\Core\Translation\Translator;
use Thelia\Form\BaseForm;
use Thelia\Form\StandardDescriptionFieldsTrait;

/**
 * Created by JetBrains PhpStorm.
 * Date: 9/18/13
 * Time: 3:56 PM.
 *
 * Form allowing to process an image
 *
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 */
abstract class ImageModification extends BaseForm
{
    use StandardDescriptionFieldsTrait;

    /**
     * {@inheritdoc}
     */
    protected function buildForm()
    {
        $translator = Translator::getInstance();

        $this->formBuilder
            ->add(
                'file',
                FileType::class,
                [
                    'required' => false,
                    'constraints' => [
                        new Image([
                            //'minWidth' => 200,
                            //'minHeight' => 200
                        ]),
                    ],
                    'label' => $translator->trans('Replace current image by this file'),
                    'label_attr' => [
                        'for' => 'file',
                    ],
                ]
            )
            // Is this image online ?
            ->add(
                'visible',
                CheckboxType::class,
                [
                    'constraints' => [],
                    'required' => false,
                    'label' => $translator->trans('This image is online'),
                    'label_attr' => [
                        'for' => 'visible_create',
                    ],
                ]
            )
        ;

        // Add standard description fields
        $this->addStandardDescFields();
    }
}
