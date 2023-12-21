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

namespace Carousel\Form;

use Carousel\Carousel;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Validator\Constraints\Image;
use Thelia\Core\Translation\Translator;
use Thelia\Form\BaseForm;

/**
 * Class CarouselImageForm.
 *
 * @author manuel raynaud <mraynaud@openstudio.fr>
 */
class CarouselImageForm extends BaseForm
{
    protected function buildForm(): void
    {
        $translator = Translator::getInstance();
        $this->formBuilder
            ->add(
                'file',
                FileType::class,
                [
                    'constraints' => [
                        new Image(),
                    ],
                    'label' => $translator->trans('Carousel image', [], Carousel::DOMAIN_NAME),
                    'label_attr' => [
                        'for' => 'file',
                    ],
                ]
            );
    }
}
