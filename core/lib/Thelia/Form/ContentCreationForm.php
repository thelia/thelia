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

use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Validator\Constraints\NotBlank;
use Thelia\Core\Translation\Translator;

class ContentCreationForm extends BaseForm
{
    protected function buildForm(): void
    {
        $this->formBuilder
            ->add('title', TextType::class, [
                'constraints' => [
                    new NotBlank(),
                ],
                'label' => Translator::getInstance()->trans('Content title *'),
                'label_attr' => [
                    'for' => 'title',
                ],
            ])
            ->add('default_folder', IntegerType::class, [
                'label' => Translator::getInstance()->trans('Default folder *'),
                'constraints' => [
                    new NotBlank(),
                ],
                'label_attr' => ['for' => 'default_folder'],
            ])
            ->add('locale', TextType::class, [
                'constraints' => [
                    new NotBlank(),
                ],
            ])
            ->add('visible', IntegerType::class, [
                'label' => Translator::getInstance()->trans('This content is online.'),
                'label_attr' => ['for' => 'visible_create'],
            ])
        ;
    }

    public static function getName(): string
    {
        return 'thelia_content_creation';
    }
}
