<?php

declare(strict_types=1);

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Thelia\Form\Area;

use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Validator\Constraints\NotBlank;
use Thelia\Core\Translation\Translator;
use Thelia\Form\BaseForm;

/**
 * Class AreaCreateForm.
 *
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class AreaCreateForm extends BaseForm
{
    protected function buildForm(): void
    {
        $this->formBuilder
            ->add(
                'name',
                TextType::class,
                [
                    'constraints' => [
                        new NotBlank(),
                    ],
                    'label' => Translator::getInstance()->trans('Shipping zone name'),
                    'label_attr' => [
                        'for' => 'shipping_name',
                    ],
                    'attr' => [
                        'placeholder' => Translator::getInstance()->trans('A name such as Europe or Overseas'),
                    ],
                ]
            )
        ;
    }

    /**
     * @return string the name of you form. This name must be unique
     */
    public static function getName(): string
    {
        return 'thelia_area_creation';
    }
}
