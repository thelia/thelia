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

use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Validator\Constraints\NotBlank;
use Thelia\Core\Translation\Translator;

class TemplateCreationForm extends BaseForm
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
                    'label' => Translator::getInstance()->trans('Template Name *'),
                    'label_attr' => [
                        'for' => 'name',
                    ], ],
            )
            ->add(
                'locale',
                TextType::class,
                [
                    'constraints' => [
                        new NotBlank(),
                    ], ],
            );
    }

    public static function getName(): string
    {
        return 'thelia_template_creation';
    }
}
