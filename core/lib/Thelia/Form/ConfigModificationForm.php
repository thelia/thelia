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

namespace Thelia\Form;

use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Validator\Constraints\GreaterThan;
use Symfony\Component\Validator\Constraints\NotBlank;
use Thelia\Core\Translation\Translator;

class ConfigModificationForm extends BaseForm
{
    use StandardDescriptionFieldsTrait;

    protected function buildForm(): void
    {
        $this->formBuilder
            ->add('id', HiddenType::class, [
                'constraints' => [
                    new GreaterThan(
                        ['value' => 0]
                    ),
                ],
            ])
            ->add('name', TextType::class, [
                'constraints' => [
                    new NotBlank(),
                ],
                'label' => Translator::getInstance()->trans('Name'),
                'label_attr' => [
                    'for' => 'name',
                ],
            ])
            ->add('value', TextType::class, [
                'label' => Translator::getInstance()->trans('Value'),
                'label_attr' => [
                    'for' => 'value',
                ],
            ])
            ->add('hidden', HiddenType::class, [])
            ->add('secured', HiddenType::class, [
                'label' => Translator::getInstance()->trans('Prevent variable modification or deletion, except for super-admin'),
            ])
        ;

        // Add standard description fields
        $this->addStandardDescFields();
    }

    public static function getName(): string
    {
        return 'thelia_config_modification';
    }
}
