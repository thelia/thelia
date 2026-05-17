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
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Validator\Constraints\NotBlank;
use Thelia\Model\Lang;

class CategoryCreationForm extends BaseForm
{
    protected function doBuilForm($titleHelpText): void
    {
        $this->formBuilder
            ->add(
                'title',
                TextType::class,
                [
                    'constraints' => [new NotBlank()],
                    'label' => $this->translator->trans('Category title'),
                    'label_attr' => [
                        'help' => $titleHelpText,
                    ],
                ],
            )
            ->add(
                'parent',
                IntegerType::class,
                [
                    'label' => $this->translator->trans('Parent category'),
                    'constraints' => [new NotBlank()],
                    'label_attr' => [
                        'help' => $this->translator->trans('Select the parent category of this category.'),
                    ],
                ],
            )
            ->add(
                'locale',
                HiddenType::class,
                [
                    'constraints' => [new NotBlank()],
                ],
            )
            ->add(
                'visible',
                IntegerType::class, // Should be checkbox, but this is not API compatible, see #1199
                [
                    'required' => false,
                    'label' => $this->translator->trans('This category is online'),
                ],
            );
    }

    protected function buildForm(): void
    {
        $this->doBuilForm(
            $this->translator->trans(
                'Enter here the category title in the default language (%title%)',
                ['%title%' => Lang::getDefaultLanguage()->getTitle()],
            ),
        );
    }

    public static function getName(): string
    {
        return 'thelia_category_creation';
    }
}
